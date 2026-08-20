<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Application\Audit;

final class ActorContext
{
    public function __construct(
        public readonly string $type,
        public readonly ?int $ownerUserId = null,
        public readonly ?int $integrationClientId = null,
        public readonly ?string $externalSubject = null,
        public readonly ?string $issuer = null,
        public readonly string $correlationId = '',
    ) {
    }
}
