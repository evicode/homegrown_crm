<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Application\Audit;

use DateTimeInterface;
use PDO;

final class AuditWriter
{
    /** @param array<string, scalar|null> $metadata */
    public function write(
        PDO $pdo,
        ActorContext $actor,
        string $operation,
        ?string $entityType,
        int|string|null $entityId,
        array $metadata,
        DateTimeInterface $occurredAt,
    ): void {
        $statement = $pdo->prepare(
            'INSERT INTO audit_events
             (actor_type, owner_user_id, integration_client_id, external_subject, issuer, operation,
              entity_type, entity_id, correlation_id, metadata_json, occurred_at)
             VALUES
             (:actor_type, :owner_user_id, :integration_client_id, :external_subject, :issuer, :operation,
              :entity_type, :entity_id, :correlation_id, :metadata_json, :occurred_at)'
        );
        $statement->execute([
            'actor_type' => $actor->type,
            'owner_user_id' => $actor->ownerUserId,
            'integration_client_id' => $actor->integrationClientId,
            'external_subject' => $actor->externalSubject,
            'issuer' => $actor->issuer,
            'operation' => $operation,
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'correlation_id' => $actor->correlationId,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'occurred_at' => $occurredAt->format('Y-m-d H:i:s.u'),
        ]);
    }
}
