<?php

declare(strict_types=1);

use Dreamsmith\Campaign\Application\Audit\AuditWriter;
use Dreamsmith\Campaign\Bootstrap;
use Dreamsmith\Campaign\Http\Request;
use Dreamsmith\Campaign\Integration\IntegrationRepository;
use Dreamsmith\Campaign\Integration\IntegrationService;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Support\SystemClock;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$database = new Database(require $root . '/config/database.php');
$config = require $root . '/config/integrations.php';
$owner = (int) $database->pdo()->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
if ($owner < 1) {
    fwrite(STDERR, "No owner account exists. Create one before running this check.\n");
    exit(2);
}

$service = new IntegrationService($database, new AuditWriter(), new SystemClock(), $config);
$created = $service->create($owner, 'Temporary external acceptance check', ['campaign:read', 'reports:read'], 'acceptance-' . bin2hex(random_bytes(6)));
$clientId = $created['client_id'];
$token = $created['token'];
$application = Bootstrap::create($root);
$headers = ['authorization' => 'Bearer ' . $token, 'content-type' => 'application/json'];

try {
    $api = $application->run(new Request('GET', '/api/v1/capabilities', $headers, requestId: 'acceptance-api-001'));
    if ($api->status !== 200) throw new RuntimeException('API capability discovery failed with HTTP ' . $api->status . '.');
    $init = $application->run(new Request('POST', '/mcp', $headers, rawBody: json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize','params'=>['protocolVersion'=>'2025-11-25','capabilities'=>new stdClass(),'clientInfo'=>['name'=>'acceptance','version'=>'1.0']]], JSON_THROW_ON_ERROR), requestId: 'acceptance-mcp-001'));
    if ($init->status < 200 || $init->status >= 300) throw new RuntimeException('MCP initialization failed with HTTP ' . $init->status . '.');
    $current = (new IntegrationRepository($database->pdo()))->client($clientId);
    if ($current === null) throw new RuntimeException('Temporary integration disappeared.');
    $service->revoke($clientId, $owner, (int) $current['version'], 'Acceptance check complete', 'acceptance-revoke-' . bin2hex(random_bytes(6)));
    $revoked = $application->run(new Request('GET', '/api/v1/capabilities', $headers, requestId: 'acceptance-api-002'));
    if ($revoked->status !== 401) throw new RuntimeException('Revoked token was still accepted.');
    echo "PASS external API access, MCP initialization, and immediate token revocation\n";
} finally {
    unset($token);
}
