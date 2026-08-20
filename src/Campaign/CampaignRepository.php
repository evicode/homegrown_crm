<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Campaign;

use PDO;

final class CampaignRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT c.*, (s.active_campaign_id = c.id) AS is_active
             FROM campaigns c CROSS JOIN application_settings s WHERE s.id = 1
             ORDER BY c.start_date DESC, c.id DESC'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, bool $lock = false): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM campaigns WHERE id = :id' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $id]);
        $campaign = $statement->fetch();
        return is_array($campaign) ? $campaign : null;
    }

    /** @return array<string,array<string,mixed>> */
    public function targets(int $campaignId, bool $lock = false): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM campaign_targets WHERE campaign_id = :campaign_id ORDER BY id' . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute(['campaign_id' => $campaignId]);
        $result = [];
        foreach ($statement->fetchAll() as $target) {
            $result[(string) $target['metric_key']] = $target;
        }
        return $result;
    }

    /** @return array<string,mixed> */
    public function settings(bool $lock = false): array
    {
        $settings = $this->pdo->query('SELECT * FROM application_settings WHERE id = 1' . ($lock ? ' FOR UPDATE' : ''))->fetch();
        if (!is_array($settings)) {
            throw new \RuntimeException('Application settings singleton is missing.');
        }
        return $settings;
    }
}
