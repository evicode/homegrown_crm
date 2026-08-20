<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Campaign;

use DateTimeZone;
use Dreamsmith\Campaign\Application\Audit\ActorContext;
use Dreamsmith\Campaign\Application\Audit\AuditWriter;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Support\Clock;
use PDO;

final class CampaignService
{
    public function __construct(
        private readonly Database $database,
        private readonly AuditWriter $audit,
        private readonly Clock $clock,
    ) {
    }

    public function create(CampaignInput $input, int $ownerId, string $correlationId): int
    {
        return $this->database->transaction(function (PDO $pdo) use ($input, $ownerId, $correlationId): int {
            $statement = $pdo->prepare('INSERT INTO campaigns (name, start_date, end_date) VALUES (:name, :start_date, :end_date)');
            $statement->execute(['name' => $input->name, 'start_date' => $input->startDate, 'end_date' => $input->endDate]);
            $campaignId = (int) $pdo->lastInsertId();
            $target = $pdo->prepare('INSERT INTO campaign_targets (campaign_id, metric_key, target_value) VALUES (:campaign_id, :metric_key, :target_value)');
            foreach ($input->targets as $key => $value) {
                $target->execute(['campaign_id' => $campaignId, 'metric_key' => $key, 'target_value' => $value]);
            }
            $this->audit->write($pdo, $this->actor($ownerId, $correlationId), 'campaign.created', 'campaign', $campaignId, [
                'name' => $input->name,
                'start_date' => $input->startDate,
                'end_date' => $input->endDate,
            ], $this->clock->now());
            return $campaignId;
        });
    }

    /** @param array<string,int> $targetVersions */
    public function update(int $campaignId, CampaignInput $input, int $campaignVersion, array $targetVersions, int $ownerId, string $correlationId): void
    {
        $this->database->transaction(function (PDO $pdo) use ($campaignId, $input, $campaignVersion, $targetVersions, $ownerId, $correlationId): void {
            $repository = new CampaignRepository($pdo);
            $campaign = $repository->find($campaignId, true);
            if ($campaign === null) {
                throw new \OutOfBoundsException('Campaign not found.');
            }
            $targets = $repository->targets($campaignId, true);
            if ((int) $campaign['version'] !== $campaignVersion) {
                throw new StaleCampaignVersion('The campaign changed while you were editing.');
            }
            foreach (CampaignMetrics::definitions() as $key => $_definition) {
                if (!isset($targets[$key], $targetVersions[$key]) || (int) $targets[$key]['version'] !== $targetVersions[$key]) {
                    throw new StaleCampaignVersion('A campaign target changed while you were editing.');
                }
            }

            $statement = $pdo->prepare(
                'UPDATE campaigns SET name = :name, start_date = :start_date, end_date = :end_date, version = version + 1 WHERE id = :id'
            );
            $statement->execute(['name' => $input->name, 'start_date' => $input->startDate, 'end_date' => $input->endDate, 'id' => $campaignId]);
            $updateTarget = $pdo->prepare(
                'UPDATE campaign_targets SET target_value = :target_value, version = version + 1 WHERE id = :id'
            );
            $changed = [];
            $beforeTargets = [];
            $afterTargets = [];
            foreach ($input->targets as $key => $value) {
                if ((int) $targets[$key]['target_value'] !== $value) {
                    $updateTarget->execute(['target_value' => $value, 'id' => $targets[$key]['id']]);
                    $changed[] = $key;
                    $beforeTargets[$key] = (int) $targets[$key]['target_value'];
                    $afterTargets[$key] = $value;
                }
            }
            $before = ['name' => $campaign['name'], 'start_date' => $campaign['start_date'], 'end_date' => $campaign['end_date'], 'targets' => $beforeTargets];
            $after = ['name' => $input->name, 'start_date' => $input->startDate, 'end_date' => $input->endDate, 'targets' => $afterTargets];
            $this->audit->write($pdo, $this->actor($ownerId, $correlationId), 'campaign.updated', 'campaign', $campaignId, [
                'changed_targets' => implode(',', $changed),
                'previous_version' => (int) $campaign['version'],
                'before' => json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'after' => json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ], $this->clock->now());
        });
    }

    public function activate(int $campaignId, int $settingsVersion, int $ownerId, string $correlationId): void
    {
        $this->database->transaction(function (PDO $pdo) use ($campaignId, $settingsVersion, $ownerId, $correlationId): void {
            $repository = new CampaignRepository($pdo);
            $settings = $repository->settings(true);
            if ((int) $settings['version'] !== $settingsVersion) {
                throw new StaleCampaignVersion('Campaign settings changed before activation.');
            }
            if ($repository->find($campaignId, true) === null) {
                throw new \OutOfBoundsException('Campaign not found.');
            }
            $statement = $pdo->prepare('UPDATE application_settings SET active_campaign_id = :campaign_id, version = version + 1 WHERE id = 1');
            $statement->execute(['campaign_id' => $campaignId]);
            $this->audit->write($pdo, $this->actor($ownerId, $correlationId), 'campaign.activated', 'campaign', $campaignId, [
                'previous_campaign_id' => $settings['active_campaign_id'] === null ? null : (int) $settings['active_campaign_id'],
                'active_campaign_id' => $campaignId,
            ], $this->clock->now());
        });
    }

    public function updateTimezone(string $timezone, bool $confirmed, int $settingsVersion, int $ownerId, string $correlationId): void
    {
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new \InvalidArgumentException('Select a valid timezone.');
        }
        if (!$confirmed) {
            throw new \InvalidArgumentException('Confirm that due dates and reporting boundaries may move.');
        }
        $this->database->transaction(function (PDO $pdo) use ($timezone, $settingsVersion, $ownerId, $correlationId): void {
            $repository = new CampaignRepository($pdo);
            $settings = $repository->settings(true);
            if ((int) $settings['version'] !== $settingsVersion) {
                throw new StaleCampaignVersion('Application settings changed while you were editing.');
            }
            $statement = $pdo->prepare('UPDATE application_settings SET owner_timezone = :timezone, version = version + 1 WHERE id = 1');
            $statement->execute(['timezone' => $timezone]);
            $this->audit->write($pdo, $this->actor($ownerId, $correlationId), 'settings.timezone_changed', 'application_settings', 1, [
                'previous_timezone' => (string) $settings['owner_timezone'],
                'timezone' => $timezone,
            ], $this->clock->now());
        });
    }

    private function actor(int $ownerId, string $correlationId): ActorContext
    {
        return new ActorContext('owner', ownerUserId: $ownerId, correlationId: $correlationId);
    }
}
