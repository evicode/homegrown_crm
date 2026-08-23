<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Presentation;

use Dreamsmith\Campaign\Http\Router;

final class NavigationBuilder
{
    public function __construct(private readonly Router $router)
    {
    }

    /** @return list<array{label:string,url:string,current:bool}> */
    public function build(string $current, bool $hasActiveCampaign): array
    {
        $campaignDestination = $hasActiveCampaign ? null : 'campaign.settings';
        $items = [
            ['dashboard', 'dashboard', 'Dashboard'],
            ['work.index', $campaignDestination ?? 'work.index', 'Daily work'],
            ['prospects.index', 'prospects.index', 'Prospects'],
            ['opportunities.index', 'opportunities.index', 'Opportunities'],
            ['companies.index', 'companies.index', 'Companies'],
            ['lead-finder.index', 'lead-finder.index', 'Lead Finder'],
            ['lead-finder.profile', 'lead-finder.profile', 'Ideal customer profile'],
            ['data.index', 'data.index', 'Data tools'],
            ['campaign.settings', 'campaign.settings', 'Campaigns'],
            ['integrations.index', 'integrations.index', 'Integrations'],
        ];
        return array_map(fn (array $item): array => [
            'label' => $item[2],
            'url' => $this->router->url($item[1]),
            'current' => $current === $item[0],
        ], $items);
    }
}
