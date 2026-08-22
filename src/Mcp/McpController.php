<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Mcp;

use Dreamsmith\Campaign\Campaign\CampaignRepository;
use Dreamsmith\Campaign\Http\Request;
use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Integration\BearerTokenGuard;
use Dreamsmith\Campaign\Integration\CapabilityCatalog;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Reporting\CampaignReportingService;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;

final class McpController
{
    public function __construct(private readonly Database $database, private readonly BearerTokenGuard $tokens, private readonly CapabilityCatalog $capabilities, private readonly string $sessionDirectory) {}

    public function handle(Request $request): Response
    {
        try {
            $actor = $this->tokens->authenticate($request);
            $server = Server::builder()->setServerInfo('Dreamsmith Campaign', '1.0.0')->setProtocolVersion(ProtocolVersion::V2025_11_25)->setSession(new FileSessionStore($this->sessionDirectory, 900));
            $allowed = $this->capabilities->discover($actor);
            if (in_array('campaign.get_active', $allowed, true)) $server->addTool(fn (): array => $this->campaign(), 'get_active_campaign', 'Active campaign', 'Returns active campaign context.');
            if (in_array('report.get_campaign', $allowed, true)) $server->addTool(fn (): array => $this->report(), 'get_campaign_report', 'Campaign report', 'Returns the active campaign dashboard report.');
            $factory = new Psr17Factory(); $psr = $factory->createServerRequest($request->method, 'http://localhost' . $request->path, $_SERVER)->withBody($factory->createStream($request->rawBody));
            foreach ($request->headers as $name => $value) $psr = $psr->withHeader($name, $value);
            $mcpResponse = $server->build()->run(new StreamableHttpTransport($psr, $factory, $factory));
            $headers = []; foreach ($mcpResponse->getHeaders() as $name => $values) $headers[$name] = implode(', ', $values);
            return new Response((string) $mcpResponse->getBody(), $mcpResponse->getStatusCode(), $headers + ['Cache-Control' => 'private, no-store']);
        } catch (\DomainException) {
            return Response::json(['jsonrpc' => '2.0', 'error' => ['code' => -32001, 'message' => 'Bearer authentication is required.'], 'id' => null], 401, ['WWW-Authenticate' => 'Bearer', 'Cache-Control' => 'private, no-store']);
        } catch (\Throwable) {
            return Response::json(['jsonrpc' => '2.0', 'error' => ['code' => -32603, 'message' => 'Internal MCP error.'], 'id' => null], 500, ['Cache-Control' => 'private, no-store']);
        }
    }

    private function campaign(): array { $repository = new CampaignRepository($this->database->pdo()); $settings = $repository->settings(); $campaign = $settings['active_campaign_id'] === null ? null : $repository->find((int) $settings['active_campaign_id']); if ($campaign === null) return ['available' => false]; return ['available' => true, 'id' => (int) $campaign['id'], 'version' => (int) $campaign['version'], 'name' => $campaign['name'], 'start_date' => $campaign['start_date'], 'end_date' => $campaign['end_date']]; }
    private function report(): array { $repository = new CampaignRepository($this->database->pdo()); $settings = $repository->settings(); $campaign = $settings['active_campaign_id'] === null ? null : $repository->find((int) $settings['active_campaign_id']); if ($campaign === null) return ['available' => false]; return ['available' => true, 'campaign_id' => (int) $campaign['id'], 'report' => (new CampaignReportingService($this->database->pdo()))->dashboard($campaign, (string) $settings['owner_timezone'])]; }
}
