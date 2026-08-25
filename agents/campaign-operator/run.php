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

function normalizedText(?string $value): string
{
    $value = strtolower(trim((string) $value));
    return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

function websiteDomain(?string $url): string
{
    $host = strtolower((string) (parse_url((string) $url, PHP_URL_HOST) ?: ''));
    return preg_replace('/^www\./', '', $host) ?? '';
}

/** @param array<string,mixed> $profile @return list<string> */
function searchPlan(array $profile): array
{
    $characteristics = [];
    foreach ($profile['positive_keywords'] ?? [] as $term => $importance) {
        if (!is_string($term) || trim($term) === '') continue;
        $characteristics[] = ['term' => trim($term), 'importance' => max(1, (int) $importance)];
    }
    usort($characteristics, static fn (array $left, array $right): int => $right['importance'] <=> $left['importance']);
    foreach ($profile['required_any'] ?? [] as $term) {
        if (!is_string($term) || trim($term) === '') continue;
        $characteristics[] = ['term' => trim($term), 'importance' => 0];
    }
    $locations = array_values(array_filter(array_map(static fn (mixed $location): string => is_string($location) ? trim($location) : '', $profile['preferred_locations'] ?? [])));
    $queries = [];
    foreach ($characteristics as $characteristic) {
        $term = $characteristic['term'];
        if ($locations === []) $queries[] = $term . ' companies';
        else foreach (array_slice($locations, 0, 3) as $location) $queries[] = $term . ' companies in ' . $location;
    }
    if ($queries === [] && is_string($profile['description'] ?? null) && trim($profile['description']) !== '') $queries[] = trim($profile['description']) . ' companies';
    $queries = array_values(array_unique(array_filter($queries, static fn (string $query): bool => strlen($query) <= 300)));
    return array_slice($queries, 0, 8);
}

/** @param list<string> $queries */
function printSearchPlan(array $queries): void
{
    echo "SUGGESTED COMPANY SEARCHES\n";
    if ($queries === []) {
        echo "Add at least one required or scored characteristic to the Ideal Customer Profile first.\n";
        return;
    }
    echo "These come directly from your Ideal Customer Profile. Edit one before searching if needed.\n\n";
    foreach ($queries as $number => $query) echo ($number + 1) . '. ' . $query . "\n";
}

/** @param list<array<string,mixed>> $companies @param list<array<string,mixed>> $prospects @return array<string,mixed> */
function duplicateCheck(array $candidate, array $companies, array $prospects): array
{
    $name = normalizedText($candidate['name'] ?? null);
    $domain = websiteDomain($candidate['website'] ?? null);
    $address = normalizedText($candidate['address'] ?? null);
    $companyMatches = [];
    foreach ($companies as $company) {
        if (!is_array($company)) continue;
        $sameName = $name !== '' && normalizedText($company['name'] ?? null) === $name;
        $sameDomain = $domain !== '' && websiteDomain($company['website'] ?? null) === $domain;
        $sameAddress = $address !== '' && normalizedText($company['location'] ?? null) === $address;
        if ($sameName || $sameDomain || $sameAddress) $companyMatches[] = ['id' => (int) ($company['id'] ?? 0), 'by' => $sameDomain ? 'website' : ($sameAddress ? 'address' : 'name')];
    }
    $prospectMatches = [];
    foreach ($prospects as $prospect) {
        if (!is_array($prospect) || $name === '' || normalizedText($prospect['company_name'] ?? null) !== $name) continue;
        $prospectMatches[] = (int) ($prospect['id'] ?? 0);
    }
    return [
        'already_in_crm' => $companyMatches !== [] || $prospectMatches !== [],
        'matching_companies' => $companyMatches,
        'matching_prospect_ids' => array_values(array_filter($prospectMatches)),
    ];
}

/** @param array<string,mixed> $result */
function printLeadReview(array $result): void
{
    $candidates = $result['candidates'] ?? [];
    $qualified = array_filter($candidates, static fn (array $candidate): bool => ($candidate['fit'] ?? 'not_fit') !== 'not_fit');
    echo "LEAD REVIEW\n";
    echo 'Search: ' . ($result['query'] ?? '') . "\n";
    echo 'Found: ' . count($candidates) . ' | Matches: ' . count($qualified) . ' | Submitted: ' . ($result['saved_count'] ?? 0) . '/' . ($result['save_limit'] ?? 0) . "\n\n";
    foreach ($candidates as $number => $candidate) {
        $fit = match ($candidate['fit'] ?? 'not_fit') { 'strong_fit' => 'STRONG MATCH', 'possible_fit' => 'POSSIBLE MATCH', default => 'NOT A MATCH' };
        echo sprintf('%02d. %s — %d importance points%s', $number + 1, $fit, (int) ($candidate['score'] ?? 0), !empty($candidate['already_in_crm']) ? ' — ALREADY IN CRM' : '') . "\n";
        echo '    ' . ($candidate['name'] ?? 'Unnamed company') . "\n";
        $details = array_filter([$candidate['category'] ?? null, $candidate['address'] ?? null, $candidate['website'] ?? null], 'is_string');
        if ($details !== []) echo '    ' . implode(' · ', $details) . "\n";
        echo '    Why: ' . implode('; ', $candidate['reasons'] ?? []) . "\n";
        if (!empty($candidate['matching_companies'])) echo '    Existing company matches: ' . implode(', ', array_map(static fn (array $match): string => '#' . $match['id'] . ' (' . $match['by'] . ')', $candidate['matching_companies'])) . "\n";
        if (!empty($candidate['matching_prospect_ids'])) echo '    Existing prospects: ' . implode(', ', array_map(static fn (int $id): string => '#' . $id, $candidate['matching_prospect_ids'])) . "\n";
        $submission = $candidate['queue_submission'] ?? null;
        if (is_array($submission)) {
            $queuedCandidate = $submission['candidate'] ?? [];
            if (is_array($queuedCandidate) && !empty($queuedCandidate['already_exists'])) echo '    Queue: already present (#' . ($queuedCandidate['id'] ?? '?') . ', ' . ($queuedCandidate['status'] ?? 'unknown') . ")\n";
            elseif (!empty($submission['skipped'])) echo '    Queue: ' . $submission['skipped'] . "\n";
            elseif (is_array($queuedCandidate) && isset($queuedCandidate['id'])) echo '    Queue: submitted (#' . $queuedCandidate['id'] . ")\n";
        }
        echo "\n";
    }
}

/** @param array<string,mixed> $candidate @param array<string,mixed> $profile @return array<string,mixed> */
function qualify(array $candidate, array $profile): array
{
    $websiteText = publicWebsiteText(is_string($candidate['website'] ?? null) ? $candidate['website'] : null);
    $fields = ['company name' => $candidate['name'] ?? null, 'address' => $candidate['address'] ?? null, 'business category' => $candidate['category'] ?? null, 'public website' => $websiteText];
    $matchSources = static function (string $term) use ($fields): array {
        $term = strtolower($term); $sources = [];
        foreach ($fields as $source => $text) if ($term !== '' && is_string($text) && str_contains(strtolower($text), $term)) $sources[] = $source;
        return $sources;
    };
    $matches = static fn (string $term): bool => $matchSources($term) !== [];
    $sourceLabel = static fn (string $term): string => implode(', ', $matchSources($term));
    $reasons = []; $score = 0;
    foreach ($profile['negative_keywords'] as $term) {
        if (is_string($term) && $matches($term)) return ['fit' => 'not_fit', 'score' => 0, 'reasons' => ["Excluded: {$term} (found in " . $sourceLabel($term) . ')'], 'website_checked' => $websiteText !== ''];
    }
    $required = array_values(array_filter($profile['required_any'], 'is_string'));
    $requiredMatches = array_values(array_filter($required, $matches));
    if ($required !== [] && $requiredMatches === []) return ['fit' => 'not_fit', 'score' => 0, 'reasons' => ['No required characteristic matched.'], 'website_checked' => $websiteText !== ''];
    foreach ($requiredMatches as $term) $reasons[] = "Required characteristic: {$term} (found in " . $sourceLabel($term) . ')';
    foreach ($profile['positive_keywords'] as $term => $weight) {
        if (is_string($term) && $matches($term)) { $points = max(1, (int) $weight); $score += $points; $reasons[] = "Characteristic: {$term} (+{$points}; found in " . $sourceLabel($term) . ')'; }
    }
    foreach ($profile['preferred_locations'] ?? [] as $location) {
        if (is_string($location) && $matches($location)) { $score += 1; $reasons[] = "Preferred location: {$location} (+1; found in " . $sourceLabel($location) . ')'; }
    }
    $minimum = max(1, (int) ($profile['minimum_score'] ?? 1));
    $strong = max($minimum, (int) ($profile['strong_fit_score'] ?? $minimum));
    return ['fit' => $score >= $strong ? 'strong_fit' : ($score >= $minimum ? 'possible_fit' : 'not_fit'), 'score' => $score, 'reasons' => $reasons === [] ? ['No matching characteristics found.'] : $reasons, 'website_checked' => $websiteText !== ''];
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
    if ($command === 'plan') {
        $profile = toolData(callTool($endpoint, $token, $sessionId, $id++, 'get_lead_profile', []));
        if (!is_array($profile['positive_keywords'] ?? null) || !is_array($profile['required_any'] ?? null)) throw new RuntimeException('The MCP token needs lead_profiles:read and the CRM ideal customer profile must be available.');
        $queries = searchPlan($profile);
        if (in_array('--json', $argv, true)) echo json_encode(['profile' => $profile['description'] ?? null, 'queries' => $queries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        else printSearchPlan($queries);
        exit(0);
    }
    if ($command === 'find') {
        $query = trim((string) ($argv[2] ?? ''));
        $placesKey = getenv('GOOGLE_PLACES_API_KEY') ?: '';
        if ($query === '') throw new InvalidArgumentException('Use find <ideal-customer search query>.');
        if ($placesKey === '') throw new RuntimeException('Set GOOGLE_PLACES_API_KEY before using lead discovery.');
        $profile = toolData(callTool($endpoint, $token, $sessionId, $id++, 'get_lead_profile', []));
        if (!is_array($profile['required_any'] ?? null) || !is_array($profile['positive_keywords'] ?? null) || !is_array($profile['negative_keywords'] ?? null)) throw new RuntimeException('The MCP token needs lead_profiles:read and the CRM ideal customer profile must be available.');
        $places = placeSearch($query, $placesKey)['places'] ?? [];
        $save = in_array('--save', $argv, true);
        $maxSaves = (int) (getenv('CAMPAIGN_OPERATOR_MAX_SAVED_CANDIDATES') ?: 10);
        $maxSaves = min(20, max(1, $maxSaves));
        $savedCount = 0;
        $candidates = [];
        foreach ($places as $place) {
            $name = trim((string) ($place['displayName']['text'] ?? ''));
            if ($name === '') continue;
            $candidate = [
                'source' => 'google_places', 'source_id' => $place['id'] ?? null, 'name' => $name,
                'website' => $place['websiteUri'] ?? null, 'address' => $place['formattedAddress'] ?? null,
                'category' => $place['primaryType'] ?? null, 'business_status' => $place['businessStatus'] ?? null,
            ];
            try {
                $existing = toolData(callTool($endpoint, $token, $sessionId, $id++, 'search_companies', ['query' => $name]));
                $prospects = toolData(callTool($endpoint, $token, $sessionId, $id++, 'search_prospects', ['query' => $name]));
                $domain = websiteDomain($place['websiteUri'] ?? null);
                if ($domain !== '') {
                    $domainCompanies = toolData(callTool($endpoint, $token, $sessionId, $id++, 'search_companies', ['query' => $domain]));
                    $existing['items'] = array_merge($existing['items'] ?? [], $domainCompanies['items'] ?? []);
                }
                $candidate += duplicateCheck($candidate, $existing['items'] ?? [], $prospects['items'] ?? []);
                $candidate += qualify($candidate, $profile);
                if ($save && !$candidate['already_in_crm'] && $candidate['fit'] !== 'not_fit' && $savedCount < $maxSaves) {
                    $saved = callTool($endpoint, $token, $sessionId, $id++, 'propose_lead_candidate', [
                        'idempotency_key' => bin2hex(random_bytes(16)), 'source' => $candidate['source'], 'source_id' => (string) $candidate['source_id'],
                        'search_query' => $query, 'name' => $candidate['name'], 'website' => $candidate['website'], 'address' => $candidate['address'],
                        'category' => $candidate['category'], 'business_status' => $candidate['business_status'], 'fit' => $candidate['fit'],
                        'score' => $candidate['score'], 'evidence' => $candidate['reasons'],
                    ]);
                    $candidate['queue_submission'] = toolData($saved);
                    $savedCount++;
                } elseif ($save && !$candidate['already_in_crm'] && $candidate['fit'] !== 'not_fit' && $savedCount >= $maxSaves) {
                    $candidate['queue_submission'] = ['skipped' => 'Per-run save limit reached.'];
                }
            } catch (Throwable) {
                $candidate += ['already_in_crm' => false, 'matching_companies' => [], 'matching_prospect_ids' => [], 'fit' => 'not_fit', 'score' => 0, 'reasons' => []];
                $candidate['reasons'][] = 'Research could not be completed for this company. Check the CRM queue before retrying.';
                $candidate['research_error'] = true;
                if (!isset($candidate['queue_submission'])) $candidate['queue_submission'] = ['skipped' => 'Could not confirm queue submission. Check the CRM queue before retrying.'];
            }
            $candidates[] = $candidate;
        }
        $result = ['query' => $query, 'profile' => $profile['description'] ?? null, 'saved_count' => $savedCount, 'save_limit' => $maxSaves, 'candidates' => $candidates];
        if (in_array('--json', $argv, true)) echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        else printLeadReview($result);
        exit(0);
    }
    if ($command !== 'call' || !isset($argv[2], $argv[3])) throw new InvalidArgumentException('Use brief, plan, find, discover, or call <tool> <json-arguments>.');
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
