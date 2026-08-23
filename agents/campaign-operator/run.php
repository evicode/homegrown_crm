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

/** @return array<string,mixed> */
function placeSearch(string $query, string $apiKey): array
{
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $apiKey,
            'X-Goog-FieldMask: places.id,places.displayName,places.websiteUri,places.formattedAddress,places.primaryType,places.businessStatus',
        ]),
        'content' => json_encode(['textQuery' => $query, 'maxResultCount' => 20], JSON_THROW_ON_ERROR),
        'ignore_errors' => true,
        'timeout' => 20,
    ]]);
    $body = file_get_contents('https://places.googleapis.com/v1/places:searchText', false, $context);
    if ($body === false) throw new RuntimeException('Could not reach Google Places.');
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) throw new RuntimeException('Google Places returned a non-JSON response.');
    if (isset($decoded['error'])) throw new RuntimeException((string) ($decoded['error']['message'] ?? 'Google Places search failed.'));
    return $decoded;
}

/** @return array<string,mixed> */
function toolData(array $response): array
{
    $content = $response['result']['content'][0]['text'] ?? null;
    $decoded = is_string($content) ? json_decode($content, true) : null;
    return is_array($decoded) ? $decoded : [];
}

/** @return array<string,mixed> */
function leadProfile(): array
{
    $path = __DIR__ . '/lead-profile.json';
    if (!is_file($path)) throw new RuntimeException('Create lead-profile.json from lead-profile.json.example before qualifying leads.');
    $profile = json_decode((string) file_get_contents($path), true);
    if (!is_array($profile) || !is_array($profile['required_any'] ?? null) || !is_array($profile['positive_keywords'] ?? null) || !is_array($profile['negative_keywords'] ?? null)) {
        throw new RuntimeException('lead-profile.json must define required_any, positive_keywords, and negative_keywords arrays.');
    }
    return $profile;
}

function publicWebsiteText(?string $url): string
{
    if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) return '';
    $parts = parse_url($url);
    if (!in_array($parts['scheme'] ?? '', ['http', 'https'], true)) return '';
    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) return '';
    $ip = gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP) !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return '';
    $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true, 'follow_location' => 0, 'max_redirects' => 0, 'header' => "User-Agent: DreamsmithCampaignLeadFinder/0.1\r\nAccept: text/html"]]);
    $html = @file_get_contents($url, false, $context);
    if (!is_string($html)) return '';
    return strtolower(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(substr($html, 0, 60000)), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''));
}

/** @param array<string,mixed> $candidate @param array<string,mixed> $profile @return array<string,mixed> */
function qualify(array $candidate, array $profile): array
{
    $websiteText = publicWebsiteText(is_string($candidate['website'] ?? null) ? $candidate['website'] : null);
    $haystack = strtolower(implode(' ', array_filter([$candidate['name'] ?? null, $candidate['address'] ?? null, $candidate['category'] ?? null, $websiteText], 'is_string')));
    $matches = static fn (string $term): bool => $term !== '' && str_contains($haystack, strtolower($term));
    $reasons = []; $score = 0;
    foreach ($profile['negative_keywords'] as $term) {
        if (is_string($term) && $matches($term)) return ['fit' => 'not_fit', 'score' => 0, 'reasons' => ["Excluded: {$term}"], 'website_checked' => $websiteText !== ''];
    }
    $required = array_values(array_filter($profile['required_any'], 'is_string'));
    $requiredMatches = array_values(array_filter($required, $matches));
    if ($required !== [] && $requiredMatches === []) return ['fit' => 'not_fit', 'score' => 0, 'reasons' => ['No required signal matched.'], 'website_checked' => $websiteText !== ''];
    foreach ($requiredMatches as $term) $reasons[] = "Required signal: {$term}";
    foreach ($profile['positive_keywords'] as $term => $weight) {
        if (is_string($term) && $matches($term)) { $points = max(1, (int) $weight); $score += $points; $reasons[] = "Matched {$term} (+{$points})"; }
    }
    foreach ($profile['preferred_locations'] ?? [] as $location) {
        if (is_string($location) && $matches($location)) { $score += 1; $reasons[] = "Preferred location: {$location} (+1)"; }
    }
    $minimum = max(1, (int) ($profile['minimum_score'] ?? 1));
    $strong = max($minimum, (int) ($profile['strong_fit_score'] ?? $minimum));
    return ['fit' => $score >= $strong ? 'strong_fit' : ($score >= $minimum ? 'possible_fit' : 'not_fit'), 'score' => $score, 'reasons' => $reasons === [] ? ['No positive evidence matched.'] : $reasons, 'website_checked' => $websiteText !== ''];
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
    if ($command === 'find') {
        $query = trim((string) ($argv[2] ?? ''));
        $placesKey = getenv('GOOGLE_PLACES_API_KEY') ?: '';
        if ($query === '') throw new InvalidArgumentException('Use find <ideal-customer search query>.');
        if ($placesKey === '') throw new RuntimeException('Set GOOGLE_PLACES_API_KEY before using lead discovery.');
        $profile = leadProfile();
        $places = placeSearch($query, $placesKey)['places'] ?? [];
        $save = in_array('--save', $argv, true);
        $candidates = [];
        foreach ($places as $place) {
            $name = trim((string) ($place['displayName']['text'] ?? ''));
            if ($name === '') continue;
            $existing = toolData(callTool($endpoint, $token, $sessionId, $id++, 'search_companies', ['query' => $name]));
            $matches = array_values(array_filter($existing['items'] ?? [], static fn (array $company): bool => strcasecmp((string) ($company['name'] ?? ''), $name) === 0));
            $candidate = [
                'source' => 'google_places', 'source_id' => $place['id'] ?? null, 'name' => $name,
                'website' => $place['websiteUri'] ?? null, 'address' => $place['formattedAddress'] ?? null,
                'category' => $place['primaryType'] ?? null, 'business_status' => $place['businessStatus'] ?? null,
                'already_in_crm' => $matches !== [], 'matching_company_ids' => array_column($matches, 'id'),
            ];
            $candidate += qualify($candidate, $profile);
            if ($save && !$candidate['already_in_crm']) {
                $saved = callTool($endpoint, $token, $sessionId, $id++, 'propose_lead_candidate', [
                    'idempotency_key' => bin2hex(random_bytes(16)), 'source' => $candidate['source'], 'source_id' => (string) $candidate['source_id'],
                    'search_query' => $query, 'name' => $candidate['name'], 'website' => $candidate['website'], 'address' => $candidate['address'],
                    'category' => $candidate['category'], 'business_status' => $candidate['business_status'], 'fit' => $candidate['fit'],
                    'score' => $candidate['score'], 'evidence' => $candidate['reasons'],
                ]);
                $candidate['queue_submission'] = toolData($saved);
            }
            $candidates[] = $candidate;
        }
        echo json_encode(['query' => $query, 'profile' => $profile['description'] ?? null, 'candidates' => $candidates], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }
    if ($command !== 'call' || !isset($argv[2], $argv[3])) throw new InvalidArgumentException('Use brief, find, discover, or call <tool> <json-arguments>.');
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
