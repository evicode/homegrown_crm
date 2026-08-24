<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Presentation;

use Dreamsmith\Campaign\Http\Router;

final class NavigationBuilder
{
    public function __construct(private readonly Router $router)
    {
    }

    /** @return list<array{label:string,url:string,current:bool,group:string}> */
    public function build(string $current, bool $hasActiveCampaign): array
    {
        $campaignDestination = $hasActiveCampaign ? null : 'campaign.settings';
        $items = [
            ['dashboard', 'dashboard', 'Dashboard', 'Workspace'],
            ['work.index', $campaignDestination ?? 'work.index', 'Daily work', 'Workspace'],
            ['prospects.index', 'prospects.index', 'Prospects', 'Customers'],
            ['opportunities.index', 'opportunities.index', 'Opportunities', 'Customers'],
            ['companies.index', 'companies.index', 'Companies', 'Customers'],
            ['lead-finder.index', 'lead-finder.index', 'Lead Finder', 'Growth'],
            ['lead-finder.profile', 'lead-finder.profile', 'Ideal customer profile', 'Growth'],
            ['data.index', 'data.index', 'Data tools', 'Administration'],
            ['campaign.settings', 'campaign.settings', 'Campaigns', 'Administration'],
            ['integrations.index', 'integrations.index', 'Integrations', 'Administration'],
        ];
        return array_map(fn (array $item): array => [
            'label' => $item[2],
            'group' => $item[3],
            'url' => $this->router->url($item[1]),
            'current' => $current === $item[0],
        ], $items);
    }
}
