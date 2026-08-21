<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Reporting;

final class ListQuery
{
    /** @param list<string> $sorts */
    public function __construct(
        public readonly string $text,
        public readonly int $page,
        public readonly int $size = 25,
        public readonly string $sort = 'updated',
        public readonly string $direction = 'desc',
    ) {
    }

    /** @param array<string,mixed> $query @param list<string> $sorts */
    public static function from(array $query, array $sorts = ['updated'], string $defaultSort = 'updated'): self
    {
        $text = trim(is_string($query['q'] ?? null) ? $query['q'] : '');
        $sort = is_string($query['sort'] ?? null) ? $query['sort'] : $defaultSort;
        $direction = is_string($query['direction'] ?? null) ? strtolower($query['direction']) : 'desc';
        return new self(
            substr($text, 0, 120),
            min(10000, max(1, (int) ($query['page'] ?? 1))),
            25,
            in_array($sort, $sorts, true) ? $sort : $defaultSort,
            in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc',
        );
    }

    public function offset(): int { return ($this->page - 1) * $this->size; }

    /** @param array<string,string|int> $filters */
    public function url(string $baseUrl, array $filters = []): string
    {
        return $baseUrl . '?' . http_build_query($filters + ['q' => $this->text, 'sort' => $this->sort, 'direction' => $this->direction, 'page' => $this->page]);
    }
}
