<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Reporting;

use Dreamsmith\Campaign\Authentication\AuthenticationService;
use Dreamsmith\Campaign\Campaign\CampaignRepository;
use Dreamsmith\Campaign\Http\Request;
use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Http\Router;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Presentation\ShellRenderer;

final class ReportingController
{
    public function __construct(private readonly AuthenticationService $auth, private readonly Database $database, private readonly ShellRenderer $shell, private readonly Router $router) {}

    public function campaign(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) return Response::redirect($this->router->url('login.form') . '?return=dashboard', 302);
        $campaigns = new CampaignRepository($this->database->pdo());
        $settings = $campaigns->settings();
        $campaign = $settings['active_campaign_id'] === null ? null : $campaigns->find((int) $settings['active_campaign_id']);
        if ($campaign === null) return Response::redirect($this->router->url('campaign.settings'), 302);
        $service = new CampaignReportingService($this->database->pdo());
        $report = $service->dashboard($campaign, (string) $settings['owner_timezone']);
        $metric = is_string($request->query['metric'] ?? null) ? $request->query['metric'] : '';
        if (!array_key_exists($metric, $report['metrics'])) $metric = '';
        return $this->shell->owner('reports/campaign.php', [
            'campaign' => $campaign, 'report' => $report, 'metric' => $metric,
            'drillDown' => $metric === '' ? [] : $service->drillDown($campaign, (string) $settings['owner_timezone'], $metric),
            'reportUrl' => $this->router->url('reports.campaign'),
            'prospectUrl' => fn (int $id): string => $this->router->url('prospects.show', ['id' => $id]),
            'opportunityUrl' => fn (int $id): string => $this->router->url('opportunities.show', ['id' => $id]),
        ], $user, 'Campaign report', 'dashboard');
    }
}
