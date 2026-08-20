<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Campaign;

final class CampaignMetrics
{
    /** @return array<string, array{label:string,default:int}> */
    public static function definitions(): array
    {
        return [
            'selected_prospects' => ['label' => 'Selected prospects', 'default' => 175],
            'personalized_contacts' => ['label' => 'Personalized outbound contacts', 'default' => 125],
            'partners_contacted' => ['label' => 'Partners contacted', 'default' => 50],
            'network_contacts' => ['label' => 'Existing-network contacts', 'default' => 40],
            'sales_conversations' => ['label' => 'Sales conversations', 'default' => 16],
            'qualified_opportunities' => ['label' => 'Qualified opportunities', 'default' => 6],
            'offers_sent' => ['label' => 'Offers sent', 'default' => 3],
            'contracts_won' => ['label' => 'Contracts won', 'default' => 1],
        ];
    }

    /** @return array<string, int> */
    public static function defaults(): array
    {
        return array_map(static fn (array $definition): int => $definition['default'], self::definitions());
    }
}
