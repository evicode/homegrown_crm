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
        // This fixed-command client parses one JSON-RPC response at a time; it does not implement SSE framing.
        'Accept: application/json',
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
    // MCP notifications such as notifications/initialized intentionally have no response body.
    if (trim($body) === '') return [[], $newSessionId ?? $sessionId];
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

/** @return list<array<string,mixed>> */
function googleCandidates(string $query,string $key):array{$places=placeSearch($query,$key)['places']??[];return array_values(array_filter(array_map(static fn(array $p):array=>['source'=>'google_places','source_id'=>$p['id']??null,'name'=>$p['displayName']['text']??null,'website'=>$p['websiteUri']??null,'address'=>$p['formattedAddress']??null,'category'=>$p['primaryType']??null,'business_status'=>$p['businessStatus']??null],$places),static fn(array $p):bool=>is_string($p['name'])&&trim($p['name'])!==''));}
/** @return list<array<string,mixed>> */
function foursquareCandidates(string $query,string $key):array{$url='https://places-api.foursquare.com/places/search?'.http_build_query(['query'=>$query,'limit'=>20,'fields'=>'fsq_place_id,name,address,locality,region,postcode,website,fsq_category_labels']);$ctx=stream_context_create(['http'=>['header'=>"Accept: application/json\r\nAuthorization: Bearer {$key}\r\nX-Places-Api-Version: 2025-06-17",'ignore_errors'=>true,'timeout'=>20]]);$body=file_get_contents($url,false,$ctx);$data=is_string($body)?json_decode($body,true):null;if(!is_array($data)||isset($data['error']))throw new RuntimeException('Foursquare search failed.');return array_values(array_filter(array_map(static function(array $p):array{$address=implode(', ',array_filter([$p['address']??null,$p['locality']??null,$p['region']??null,$p['postcode']??null],'is_string'));return['source'=>'foursquare','source_id'=>$p['fsq_place_id']??null,'name'=>$p['name']??null,'website'=>$p['website']??null,'address'=>$address?:null,'category'=>is_array($p['fsq_category_labels']??null)?implode(', ',$p['fsq_category_labels']):null,'business_status'=>null];},$data['results']??[]),static fn(array $p):bool=>is_string($p['name'])&&trim($p['name'])!==''));}
/** @return list<string> */
function selectedSources(): array
{
    global $argv;

    $supported = ['google_places', 'foursquare', 'osm'];
    foreach ($argv as $argument) {
        if (str_starts_with($argument, '--sources=')) {
            return array_values(array_intersect($supported, explode(',', substr($argument, 10))));
        }
    }

    $available = [];
    if (getenv('GOOGLE_PLACES_API_KEY')) $available[] = 'google_places';
    if (getenv('FOURSQUARE_PLACES_API_KEY')) $available[] = 'foursquare';
    if (getenv('MAPBOX_ACCESS_TOKEN') && getenv('OSM_OVERPASS_URL')) $available[] = 'osm';
    return $available;
}

/** @param list<string> $sources */
function assertConfiguredSources(array $sources): void
{
    $missing = [];
    if (in_array('google_places', $sources, true) && !getenv('GOOGLE_PLACES_API_KEY')) $missing[] = 'Google Places key';
    if (in_array('foursquare', $sources, true) && !getenv('FOURSQUARE_PLACES_API_KEY')) $missing[] = 'Foursquare key';
    if (in_array('osm', $sources, true) && (!getenv('MAPBOX_ACCESS_TOKEN') || !getenv('OSM_OVERPASS_URL'))) $missing[] = 'Mapbox token and OSM Overpass URL';
    if ($missing !== []) throw new RuntimeException('Add the required setting for every selected source: ' . implode('; ', $missing) . '.');
}
/** @param list<array<string,mixed>> $places @return list<array<string,mixed>> */
function deduplicatePlaces(array $places):array{$unique=[];foreach($places as $place){$key=websiteDomain($place['website']??null);if($key==='')$key=normalizedText($place['name']??null).'|'.normalizedText($place['address']??null);if($key===''||$key==='|')$key=(string)$place['source'].'|'.(string)$place['source_id'];if(isset($unique[$key]))continue;$unique[$key]=$place;}return array_values($unique);}
/** @return list<array<string,mixed>> */
function osmCandidates(string $query, string $mapboxToken, string $overpassUrl): array
{
    $location = preg_match('/\bin\s+(.+)$/i', $query, $matches) ? $matches[1] : $query;
    $geocodeUrl = 'https://api.mapbox.com/search/searchbox/v1/forward?' . http_build_query([
        'q' => $location,
        'types' => 'place,locality,region',
        'limit' => 1,
        'access_token' => $mapboxToken,
    ]);
    $body = @file_get_contents($geocodeUrl, false, stream_context_create(['http' => ['timeout' => 10]]));
    $geocode = is_string($body) ? json_decode($body, true) : null;
    $feature = is_array($geocode) ? ($geocode['features'][0] ?? null) : null;
    $coordinates = is_array($feature) ? ($feature['geometry']['coordinates'] ?? null) : null;
    if (!is_array($coordinates) || count($coordinates) < 2 || !is_numeric($coordinates[0]) || !is_numeric($coordinates[1])) {
        throw new RuntimeException('Mapbox could not locate the OSM search area.');
    }

    $longitude = (float) $coordinates[0];
    $latitude = (float) $coordinates[1];
    $overpassQuery = '[out:json][timeout:25];('
        . 'nwr["office"](around:10000,' . $latitude . ',' . $longitude . ');'
        . 'nwr["craft"](around:10000,' . $latitude . ',' . $longitude . ');'
        . 'nwr["industrial"](around:10000,' . $latitude . ',' . $longitude . ');'
        . ');out center 30;';
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query(['data' => $overpassQuery]),
        'timeout' => 30,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents($overpassUrl, false, $context);
    $data = is_string($response) ? json_decode($response, true) : null;
    if (!is_array($data)) throw new RuntimeException('OpenStreetMap search failed.');

    $candidates = [];
    foreach (array_slice($data['elements'] ?? [], 0, 20) as $item) {
        if (!is_array($item)) continue;
        $tags = is_array($item['tags'] ?? null) ? $item['tags'] : [];
        $name = trim((string) ($tags['name'] ?? ''));
        if ($name === '') continue;
        $address = implode(', ', array_filter([
            $tags['addr:housenumber'] ?? null,
            $tags['addr:street'] ?? null,
            $tags['addr:city'] ?? null,
        ], 'is_string'));
        $category = implode(', ', array_keys(array_intersect_key($tags, array_flip(['office', 'craft', 'industrial']))));
        $candidates[] = [
            'source' => 'openstreetmap',
            'source_id' => (string) ($item['type'] ?? '') . ' ' . (string) ($item['id'] ?? ''),
            'name' => $name,
            'website' => $tags['website'] ?? $tags['contact:website'] ?? null,
            'address' => $address ?: null,
            'category' => $category ?: null,
            'business_status' => null,
        ];
    }
    return $candidates;
}

/** @return array<string,mixed> */
function toolData(array $response): array
{
    $content = $response['result']['content'][0]['text'] ?? null;
    $decoded = is_string($content) ? json_decode($content, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function selectedCampaignId(): ?int
{
    global $argv;
    foreach ($argv as $argument) {
        if (!str_starts_with($argument, '--campaign-id=')) continue;
        $id = (int) substr($argument, strlen('--campaign-id='));
        return $id > 0 ? $id : null;
    }
    $savedId = (int) (getenv('CAMPAIGN_OPERATOR_CAMPAIGN_ID') ?: 0);
    return $savedId > 0 ? $savedId : null;
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
        echo '    Source: ' . ($candidate['source'] ?? 'unknown') . "\n";
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
        $campaignId = selectedCampaignId();
        $brief = [];
        foreach (['campaign', 'get_campaign_report', 'search_prospects'] as $tool) {
            $name = $tool === 'campaign' ? ($campaignId === null ? 'get_active_campaign' : 'get_campaign') : $tool;
            $arguments = $tool === 'campaign' ? ($campaignId === null ? [] : ['id' => $campaignId]) : ($tool === 'search_prospects' ? ['query' => '', 'campaign_id' => $campaignId] : ['campaign_id' => $campaignId]);
            $brief[$tool] = callTool($endpoint, $token, $sessionId, $id++, $name, array_filter($arguments, static fn (mixed $value): bool => $value !== null));
        }
        echo json_encode($brief, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }
    if ($command === 'campaigns') {
        $campaigns = toolData(callTool($endpoint, $token, $sessionId, $id++, 'list_campaigns', []));
        if (in_array('--json', $argv, true)) echo json_encode($campaigns, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        else {
            echo "CAMPAIGNS\n";
            foreach ($campaigns['items'] ?? [] as $campaign) echo ($campaign['id'] ?? '?') . '. ' . ($campaign['name'] ?? 'Unnamed campaign') . ' — ' . ($campaign['start_date'] ?? '?') . ' to ' . ($campaign['end_date'] ?? '?') . (!empty($campaign['is_active']) ? ' (active)' : '') . "\n";
        }
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
    if ($command === 'apply-profile') {
        if (!isset($argv[2])) throw new InvalidArgumentException('Use apply-profile <reviewed JSON draft> --approve-write.');
        if (!in_array('--approve-write', $argv, true)) throw new RuntimeException('Refusing profile update. Review the draft and add --approve-write.');
        $draft = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($draft)) throw new InvalidArgumentException('The reviewed profile draft must be a JSON object.');
        $current = toolData(callTool($endpoint, $token, $sessionId, $id++, 'get_lead_profile', []));
        if (!isset($current['version'])) throw new RuntimeException('Could not load the current ideal customer profile.');
        $payload = ['idempotency_key' => bin2hex(random_bytes(16)), 'version' => (int) $current['version']];
        foreach (['description', 'required_any', 'positive_keywords', 'negative_keywords', 'preferred_locations', 'minimum_score', 'strong_fit_score'] as $field) {
            $payload[$field] = $draft[$field] ?? $current[$field] ?? null;
        }
        try {
            $updated = toolData(callTool($endpoint, $token, $sessionId, $id++, 'update_lead_profile', $payload));
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'Tool not found')) throw new RuntimeException('This CRM token needs the lead_profiles:write scope. Create a new integration token with that scope, save it in the dashboard, then try again.');
            throw $exception;
        }
        echo json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }
    if ($command === 'find') {
        $query = trim((string) ($argv[2] ?? ''));
        $campaignId = selectedCampaignId();
        $sources = selectedSources();
        if ($query === '') throw new InvalidArgumentException('Use find <ideal-customer search query>.');
        if ($sources === []) throw new RuntimeException('Select at least one configured discovery source before using lead discovery.');
        assertConfiguredSources($sources);
        $profile = toolData(callTool($endpoint, $token, $sessionId, $id++, 'get_lead_profile', []));
        if (!is_array($profile['required_any'] ?? null) || !is_array($profile['positive_keywords'] ?? null) || !is_array($profile['negative_keywords'] ?? null)) throw new RuntimeException('The MCP token needs lead_profiles:read and the CRM ideal customer profile must be available.');
        $places = [];
        if (in_array('google_places',$sources,true)) $places = array_merge($places,googleCandidates($query,(string)getenv('GOOGLE_PLACES_API_KEY')));
        if (in_array('foursquare',$sources,true)) $places = array_merge($places,foursquareCandidates($query,(string)getenv('FOURSQUARE_PLACES_API_KEY')));
        if (in_array('osm',$sources,true)) $places = array_merge($places,osmCandidates($query,(string)getenv('MAPBOX_ACCESS_TOKEN'),(string)getenv('OSM_OVERPASS_URL')));
        $places = deduplicatePlaces($places);
        $save = in_array('--save', $argv, true);
        $maxSaves = (int) (getenv('CAMPAIGN_OPERATOR_MAX_SAVED_CANDIDATES') ?: 10);
        $maxSaves = min(20, max(1, $maxSaves));
        $savedCount = 0;
        $candidates = [];
        foreach ($places as $place) {
            $name = trim((string) ($place['name'] ?? ''));
            if ($name === '') continue;
            $candidate = [
                'source' => $place['source'] ?? 'unknown', 'source_id' => $place['source_id'] ?? null, 'name' => $name,
                'website' => $place['website'] ?? null, 'address' => $place['address'] ?? null,
                'category' => $place['category'] ?? null, 'business_status' => $place['business_status'] ?? null,
            ];
            try {
                $existing = toolData(callTool($endpoint, $token, $sessionId, $id++, 'search_companies', ['query' => $name]));
                $prospectArguments = ['query' => $name]; if ($campaignId !== null) $prospectArguments['campaign_id'] = $campaignId;
                $prospects = toolData(callTool($endpoint, $token, $sessionId, $id++, 'search_prospects', $prospectArguments));
                $domain = websiteDomain($place['website'] ?? null);
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
    if ($command !== 'call' || !isset($argv[2], $argv[3])) throw new InvalidArgumentException('Use brief, campaigns, plan, find, discover, or call <tool> <json-arguments>.');
    $tool = $argv[2];
    $arguments = json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($arguments)) throw new InvalidArgumentException('Tool arguments must be a JSON object.');
    $writes = ['create_company', 'create_contact', 'create_prospect', 'transition_prospect', 'record_interaction', 'schedule_follow_up', 'create_opportunity', 'update_lead_profile'];
    if (in_array($tool, $writes, true) && !in_array('--approve-write', $argv, true)) throw new RuntimeException('Refusing write. Review the action and add --approve-write to run it.');
    echo json_encode(callTool($endpoint, $token, $sessionId, $id, $tool, $arguments), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Campaign operator: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
