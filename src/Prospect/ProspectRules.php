<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Prospect;

final class ProspectRules
{
    /** @param array<string,mixed> $sales */
    public function __construct(private readonly array $sales)
    {
    }

    /** @return list<string> */
    public function allowedTransitions(string $status): array
    {
        return $this->sales['transitions'][$status] ?? [];
    }

    /** @return list<string> */
    public function unmet(string $segment, string $target, ?string $whyThem, int $activeSignalCount, ?string $businessProblem, bool $problem, bool $timing, bool $buyer, bool $budget): array
    {
        $unmet = [];
        if ($target === 'ready_to_contact') {
            if (in_array($segment, ['partner', 'direct'], true) && trim((string)$whyThem) === '') $unmet[] = 'why_them';
            if ($segment === 'direct' && $activeSignalCount < 1) $unmet[] = 'signal';
        }
        if ($target === 'qualified') {
            if (trim((string)$businessProblem) === '') $unmet[] = 'business_problem';
            if (!$problem) $unmet[] = 'problem_understood';
            if (!$timing) $unmet[] = 'timing_understood';
            if (!$buyer) $unmet[] = 'buyer_understood';
            if (!$budget) $unmet[] = 'budget_plausible';
        }
        return $unmet;
    }

    public function assertTransition(string $from, string $to, array $unmet): void
    {
        if (!in_array($to, $this->allowedTransitions($from), true)) throw new \DomainException("Transition from {$from} to {$to} is not allowed.");
        if ($unmet !== []) throw new \DomainException('Transition prerequisites are incomplete: ' . implode(', ', $unmet) . '.');
    }
}
