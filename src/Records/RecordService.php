<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Records;

use Dreamsmith\Campaign\Application\Audit\ActorContext;
use Dreamsmith\Campaign\Application\Audit\AuditWriter;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Support\Clock;
use PDO;

final class RecordService
{
    public function __construct(private readonly Database $database, private readonly AuditWriter $audit, private readonly Clock $clock)
    {
    }

    public function createCompany(CompanyInput $input, bool $confirmDuplicate, int $ownerId, string $correlationId): int
    {
        return $this->database->transaction(function (PDO $pdo) use ($input, $confirmDuplicate, $ownerId, $correlationId): int {
            $repository = new RecordRepository($pdo);
            $matches = $repository->companyDuplicateLabels($input);
            if ($matches !== [] && !$confirmDuplicate) {
                throw new DuplicateWarning($matches);
            }
            $statement = $pdo->prepare('INSERT INTO companies (name, normalized_name, website, website_domain, location, industry, employee_range, revenue_range, notes) VALUES (:name, :normalized_name, :website, :website_domain, :location, :industry, :employee_range, :revenue_range, :notes)');
            $statement->execute($this->companyParameters($input));
            $id = (int) $pdo->lastInsertId();
            $this->writeAudit($pdo, $ownerId, $correlationId, 'company.created', 'company', $id, ['duplicate_confirmed' => $confirmDuplicate]);
            return $id;
        });
    }

    public function updateCompany(int $id, CompanyInput $input, int $version, bool $confirmDuplicate, int $ownerId, string $correlationId): void
    {
        $this->database->transaction(function (PDO $pdo) use ($id, $input, $version, $confirmDuplicate, $ownerId, $correlationId): void {
            $repository = new RecordRepository($pdo);
            $current = $repository->company($id, true);
            $this->requireCurrent($current, $version, 'company');
            $matches = $repository->companyDuplicateLabels($input, $id);
            if ($matches !== [] && !$confirmDuplicate) {
                throw new DuplicateWarning($matches);
            }
            $parameters = $this->companyParameters($input) + ['id' => $id];
            $pdo->prepare('UPDATE companies SET name=:name, normalized_name=:normalized_name, website=:website, website_domain=:website_domain, location=:location, industry=:industry, employee_range=:employee_range, revenue_range=:revenue_range, notes=:notes, version=version+1 WHERE id=:id')->execute($parameters);
            $this->writeAudit($pdo, $ownerId, $correlationId, 'company.updated', 'company', $id, ['previous_version' => $version]);
        });
    }

    public function createContact(ContactInput $input, bool $confirmDuplicate, int $ownerId, string $correlationId): int
    {
        return $this->database->transaction(function (PDO $pdo) use ($input, $confirmDuplicate, $ownerId, $correlationId): int {
            $repository = new RecordRepository($pdo);
            $this->requireActiveCompany($repository, $input->companyId);
            $matches = $repository->contactDuplicateLabels($input);
            if ($matches !== [] && !$confirmDuplicate) {
                throw new DuplicateWarning($matches);
            }
            $statement = $pdo->prepare('INSERT INTO contacts (company_id, first_name, last_name, normalized_name, role, email, email_normalized, phone, linkedin_url) VALUES (:company_id, :first_name, :last_name, :normalized_name, :role, :email, :email_normalized, :phone, :linkedin_url)');
            $statement->execute($this->contactParameters($input));
            $id = (int) $pdo->lastInsertId();
            $this->writeAudit($pdo, $ownerId, $correlationId, 'contact.created', 'contact', $id, ['company_id' => $input->companyId, 'duplicate_confirmed' => $confirmDuplicate]);
            return $id;
        });
    }

    public function updateContact(int $id, ContactInput $input, int $version, bool $confirmDuplicate, bool $confirmArchivedReassociation, int $ownerId, string $correlationId): void
    {
        $this->database->transaction(function (PDO $pdo) use ($id, $input, $version, $confirmDuplicate, $confirmArchivedReassociation, $ownerId, $correlationId): void {
            $repository = new RecordRepository($pdo);
            $current = $repository->contact($id, true);
            $this->requireCurrent($current, $version, 'contact');
            if ($current['company_id'] !== null && $current['company_archived_at'] !== null
                && (int) $current['company_id'] !== $input->companyId && !$confirmArchivedReassociation) {
                throw new \DomainException('Confirm reassignment away from the archived company.');
            }
            $this->requireActiveCompany($repository, $input->companyId);
            $matches = $repository->contactDuplicateLabels($input, $id);
            if ($matches !== [] && !$confirmDuplicate) {
                throw new DuplicateWarning($matches);
            }
            $pdo->prepare('UPDATE contacts SET company_id=:company_id, first_name=:first_name, last_name=:last_name, normalized_name=:normalized_name, role=:role, email=:email, email_normalized=:email_normalized, phone=:phone, linkedin_url=:linkedin_url, version=version+1 WHERE id=:id')->execute($this->contactParameters($input) + ['id' => $id]);
            $this->writeAudit($pdo, $ownerId, $correlationId, 'contact.updated', 'contact', $id, [
                'previous_version' => $version,
                'previous_company_id' => $current['company_id'] === null ? null : (int) $current['company_id'],
                'company_id' => $input->companyId,
            ]);
        });
    }

    public function archiveCompany(int $id, int $version, bool $confirmContacts, int $ownerId, string $correlationId): void
    {
        $this->database->transaction(function (PDO $pdo) use ($id, $version, $confirmContacts, $ownerId, $correlationId): void {
            $repository = new RecordRepository($pdo);
            $current = $repository->company($id, true);
            $this->requireCurrent($current, $version, 'company');
            if ($current['archived_at'] !== null) {
                throw new \DomainException('Company is already archived.');
            }
            $contacts = $repository->companyContacts($id, false);
            $prospects = $pdo->prepare('SELECT COUNT(*) FROM prospects WHERE company_id=:id AND archived_at IS NULL');
            $prospects->execute(['id'=>$id]);
            if ((int)$prospects->fetchColumn() > 0) {
                throw new \DomainException('Archive or reassociate active prospects before archiving this company.');
            }
            if ($contacts !== [] && !$confirmContacts) {
                throw new \DomainException('Confirm that active contacts will remain attached to this archived company.');
            }
            $pdo->prepare('UPDATE companies SET archived_at=UTC_TIMESTAMP(6), version=version+1 WHERE id=:id')->execute(['id' => $id]);
            $this->writeAudit($pdo, $ownerId, $correlationId, 'company.archived', 'company', $id, ['active_contact_count' => count($contacts)]);
        });
    }

    public function restoreCompany(int $id, int $version, bool $confirmDuplicate, int $ownerId, string $correlationId): void
    {
        $this->database->transaction(function (PDO $pdo) use ($id, $version, $confirmDuplicate, $ownerId, $correlationId): void {
            $repository = new RecordRepository($pdo);
            $current = $repository->company($id, true);
            $this->requireCurrent($current, $version, 'company');
            if ($current['archived_at'] === null) {
                throw new \DomainException('Company is not archived.');
            }
            [$input] = CompanyInput::fromArray($current);
            $matches = $repository->companyDuplicateLabels($input, $id);
            if ($matches !== [] && !$confirmDuplicate) {
                throw new DuplicateWarning($matches);
            }
            $pdo->prepare('UPDATE companies SET archived_at=NULL, version=version+1 WHERE id=:id')->execute(['id' => $id]);
            $this->writeAudit($pdo, $ownerId, $correlationId, 'company.restored', 'company', $id, ['duplicate_confirmed' => $confirmDuplicate]);
        });
    }

    public function archiveContact(int $id, int $version, int $ownerId, string $correlationId): void
    {
        $this->toggleContactArchive($id, $version, true, false, $ownerId, $correlationId);
    }

    public function restoreContact(int $id, int $version, bool $confirmDuplicate, int $ownerId, string $correlationId): void
    {
        $this->toggleContactArchive($id, $version, false, $confirmDuplicate, $ownerId, $correlationId);
    }

    private function toggleContactArchive(int $id, int $version, bool $archive, bool $confirmDuplicate, int $ownerId, string $correlationId): void
    {
        $this->database->transaction(function (PDO $pdo) use ($id, $version, $archive, $confirmDuplicate, $ownerId, $correlationId): void {
            $repository = new RecordRepository($pdo);
            $current = $repository->contact($id, true);
            $this->requireCurrent($current, $version, 'contact');
            if ($archive) {
                $check=$pdo->prepare('SELECT COUNT(*) FROM prospects WHERE primary_contact_id=:id AND archived_at IS NULL');
                $check->execute(['id'=>$id]);
                if((int)$check->fetchColumn()>0)throw new \DomainException('Reassign or archive active prospects before archiving this contact.');
            }
            if ($archive && $current['archived_at'] !== null || !$archive && $current['archived_at'] === null) {
                throw new \DomainException($archive ? 'Contact is already archived.' : 'Contact is not archived.');
            }
            if (!$archive) {
                [$input] = ContactInput::fromArray($current);
                $this->requireActiveCompany($repository, $input->companyId);
                $matches = $repository->contactDuplicateLabels($input, $id);
                if ($matches !== [] && !$confirmDuplicate) {
                    throw new DuplicateWarning($matches);
                }
            }
            $sql = $archive ? 'UPDATE contacts SET archived_at=UTC_TIMESTAMP(6), version=version+1 WHERE id=:id' : 'UPDATE contacts SET archived_at=NULL, version=version+1 WHERE id=:id';
            $pdo->prepare($sql)->execute(['id' => $id]);
            $this->writeAudit($pdo, $ownerId, $correlationId, $archive ? 'contact.archived' : 'contact.restored', 'contact', $id, ['duplicate_confirmed' => $confirmDuplicate]);
        });
    }

    /** @param array<string,mixed>|null $current */
    private function requireCurrent(?array $current, int $version, string $type): void
    {
        if ($current === null) {
            throw new \OutOfBoundsException(ucfirst($type) . ' not found.');
        }
        if ((int) $current['version'] !== $version) {
            throw new StaleRecordVersion(ucfirst($type) . ' changed while you were editing.');
        }
    }

    private function requireActiveCompany(RecordRepository $repository, ?int $companyId): void
    {
        if ($companyId === null) {
            return;
        }
        $company = $repository->company($companyId, true);
        if ($company === null || $company['archived_at'] !== null) {
            throw new \DomainException('Select an active company or leave the contact independent.');
        }
    }

    /** @return array<string,mixed> */
    private function companyParameters(CompanyInput $input): array
    {
        return ['name'=>$input->name,'normalized_name'=>$input->normalizedName,'website'=>$input->website,'website_domain'=>$input->websiteDomain,'location'=>$input->location,'industry'=>$input->industry,'employee_range'=>$input->employeeRange,'revenue_range'=>$input->revenueRange,'notes'=>$input->notes];
    }

    /** @return array<string,mixed> */
    private function contactParameters(ContactInput $input): array
    {
        return ['company_id'=>$input->companyId,'first_name'=>$input->firstName,'last_name'=>$input->lastName,'normalized_name'=>$input->normalizedName,'role'=>$input->role,'email'=>$input->email,'email_normalized'=>$input->emailNormalized,'phone'=>$input->phone,'linkedin_url'=>$input->linkedinUrl];
    }

    /** @param array<string,scalar|null> $metadata */
    private function writeAudit(PDO $pdo, int $ownerId, string $correlationId, string $operation, string $entityType, int $entityId, array $metadata): void
    {
        $this->audit->write($pdo, new ActorContext('owner', ownerUserId: $ownerId, correlationId: $correlationId), $operation, $entityType, $entityId, $metadata, $this->clock->now());
    }
}
