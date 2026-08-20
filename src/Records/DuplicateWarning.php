<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Records;

final class DuplicateWarning extends \RuntimeException
{
    /** @param list<string> $matches */
    public function __construct(public readonly array $matches)
    {
        parent::__construct('Potential duplicate records were found.');
    }
}
