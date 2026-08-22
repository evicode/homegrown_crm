<?php

declare(strict_types=1);

const PROTOCOL_VERSION = '2025-11-25';

$command = $argv[1] ?? 'brief';
$endpoint = getenv('CAMPAIGN_OPERATOR_MCP_URL') ?: '';
$token = getenv('CAMPAIGN_OPERATOR_TOKEN') ?: '';

if ($endpoint === '' || $token === '') {
    fwrite(STDERR, "Set CAMPAIGN_OPERATOR_MCP_URL and CAMPAIGN_OPERATOR_TOKEN before running this operator.\n");
    exit(2);
}

/** @return array{0:array<string,mixed>,1:?string} */
function requestMcp(string $endpoint, string $token, array $payload, ?string $sessionId = null): array
{
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json, text/event-stream',
        'Authorization: Bearer ' . $token,
        'MCP-Protocol-Version: ' . PROTOCOL_VERSION,
    ];
    if ($sessionId !== null) $headers[] = 'Mcp-Session-Id: ' . $sessionId;
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => json_encode($payload, JSON_THROW_ON_ERROR),
        'ignore_errors' => true,
        'timeout' => 20,
    ]]);
    $body = file_get_contents($endpoint, false, $context);
    $responseHeaders = $http_response_header ?? [];
    if ($body === false) throw new RuntimeException('Could not reach the MCP endpoint.');
    $newSessionId = null;
    foreach ($responseHeaders as $header) {
        if (stripos($header, 'Mcp-Session-Id:') === 0) $newSessionId = trim(substr($header, strlen('Mcp-Session-Id:')));
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) throw new RuntimeException('MCP returned a non-JSON response.');
    if (isset($decoded['error'])) throw new RuntimeException((string) ($decoded['error']['message'] ?? 'MCP request failed.'));
    return [$decoded, $newSessionId ?? $sessionId];
}

/** @return array{0:string,1:int} */
function connect(string $endpoint, string $token): array
{
    [, $sessionId] = requestMcp($endpoint, $token, [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
        'params' => ['protocolVersion' => PROTOCOL_VERSION, 'capabilities' => new stdClass(), 'clientInfo' => ['name' => 'campaign-operator', 'version' => '0.1.0']],
    ]);
    if ($sessionId === null || $sessionId === '') throw new RuntimeException('MCP did not establish a session.');
    requestMcp($endpoint, $token, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized', 'params' => new stdClass()], $sessionId);
    return [$sessionId, 2];
}

/** @return array<string,mixed> */
function callTool(string $endpoint, string $token, string $sessionId, int $id, string $name, array $arguments): array
{
    [$response] = requestMcp($endpoint, $token, [
        'jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => $arguments],
    ], $sessionId);
    return $response;
}

try {
    [$sessionId, $id] = connect($endpoint, $token);
    if ($command === 'discover') {
        [$response] = requestMcp($endpoint, $token, ['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/list', 'params' => new stdClass()], $sessionId);
        echo json_encode($response['result'] ?? $response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }
    if ($command === 'brief') {
        $brief = [];
        foreach (['get_active_campaign', 'get_campaign_report', 'search_prospects'] as $tool) {
            $brief[$tool] = callTool($endpoint, $token, $sessionId, $id++, $tool, $tool === 'search_prospects' ? ['query' => ''] : []);
        }
        echo json_encode($brief, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }
    if ($command !== 'call' || !isset($argv[2], $argv[3])) throw new InvalidArgumentException('Use brief, discover, or call <tool> <json-arguments>.');
    $tool = $argv[2];
    $arguments = json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($arguments)) throw new InvalidArgumentException('Tool arguments must be a JSON object.');
    $writes = ['create_company', 'create_contact', 'create_prospect', 'transition_prospect', 'record_interaction', 'schedule_follow_up', 'create_opportunity'];
    if (in_array($tool, $writes, true) && !in_array('--approve-write', $argv, true)) throw new RuntimeException('Refusing write. Review the action and add --approve-write to run it.');
    echo json_encode(callTool($endpoint, $token, $sessionId, $id, $tool, $arguments), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Campaign operator: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
