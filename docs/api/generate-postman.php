<?php
/**
 * Generate a Postman v2.1 collection from docs/api/openapi-v2.yaml.
 * Folders by tag, collection-level Bearer auth, {{base_url}}/{{token}}
 * variables, path params as :vars, query params (disabled) and JSON bodies
 * derived from each operation's documented schema.
 */

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$spec = \Symfony\Component\Yaml\Yaml::parseFile($root . '/docs/api/openapi-v2.yaml');

$METHODS = ['get', 'post', 'put', 'patch', 'delete'];

/** Resolve a $ref like #/components/parameters/PerPage */
function resolveRef(array $spec, string $ref)
{
    $parts = explode('/', ltrim($ref, '#/'));
    $node = $spec;
    foreach ($parts as $p) {
        $node = $node[$p] ?? null;
        if ($node === null) return null;
    }
    return $node;
}

/** A placeholder value for a JSON schema property. */
function sampleValue(array $schema)
{
    if (isset($schema['enum']) && is_array($schema['enum'])) return $schema['enum'][0];
    switch ($schema['type'] ?? 'string') {
        case 'integer': return 0;
        case 'number':  return 0;
        case 'boolean': return false;
        case 'array':   return [];
        case 'object':  return new stdClass();
        default:        return '';
    }
}

/** Build the JSON body object from an operation's requestBody. */
function bodyFromOperation(array $op)
{
    $schema = $op['requestBody']['content']['application/json']['schema'] ?? null;
    if (! $schema || ($schema['type'] ?? '') !== 'object' || empty($schema['properties'])) {
        return null;
    }
    $required = array_flip($schema['required'] ?? []);
    $out = [];
    foreach ($schema['properties'] as $name => $prop) {
        $out[$name] = sampleValue(is_array($prop) ? $prop : []);
    }
    // Put required fields first for readability.
    uksort($out, fn ($a, $b) => (isset($required[$b]) ? 1 : 0) - (isset($required[$a]) ? 1 : 0));
    return $out;
}

/** Collect query params (in:query) from path-level + operation-level params. */
function queryParams(array $spec, array $paramLists): array
{
    $q = [];
    foreach ($paramLists as $list) {
        foreach ($list as $p) {
            if (isset($p['$ref'])) $p = resolveRef($spec, $p['$ref']) ?: [];
            if (($p['in'] ?? '') === 'query' && isset($p['name'])) {
                $q[$p['name']] = true;
            }
        }
    }
    return array_keys($q);
}

$folders = [];   // tag => item list
foreach ($spec['tags'] as $t) {
    $folders[$t['name']] = [];
}
$folders['Other'] = [];

foreach ($spec['paths'] as $path => $node) {
    $pathParams = $node['parameters'] ?? [];

    foreach ($METHODS as $method) {
        if (! isset($node[$method])) continue;
        $op = $node[$method];
        if (! is_array($op)) continue;

        $tag = $op['tags'][0] ?? 'Other';
        if (! isset($folders[$tag])) $folders[$tag] = [];

        // URL: /api/v2 + path, {param} -> :param
        $full = 'api/v2' . $path;
        $segments = array_values(array_filter(explode('/', $full), fn ($s) => $s !== ''));
        $variables = [];
        foreach ($segments as $i => $seg) {
            if (preg_match('/^\{(.+)\}$/', $seg, $m)) {
                $segments[$i] = ':' . $m[1];
                $variables[] = ['key' => $m[1], 'value' => ''];
            }
        }

        $query = [];
        foreach (queryParams($spec, [$pathParams, $op['parameters'] ?? []]) as $qp) {
            $query[] = ['key' => $qp, 'value' => '', 'disabled' => true];
        }

        $rawPath = implode('/', $segments);
        $url = [
            'raw'  => '{{base_url}}/' . $rawPath,
            'host' => ['{{base_url}}'],
            'path' => $segments,
        ];
        if ($query) $url['query'] = $query;
        if ($variables) $url['variable'] = $variables;

        $headers = [['key' => 'Accept', 'value' => 'application/json']];
        $request = [
            'method' => strtoupper($method),
            'header' => $headers,
            'url'    => $url,
        ];
        if (! empty($op['summary'])) {
            $request['description'] = $op['summary'];
        }

        $body = bodyFromOperation($op);
        if ($body !== null) {
            $request['header'][] = ['key' => 'Content-Type', 'value' => 'application/json'];
            $request['body'] = [
                'mode' => 'raw',
                'raw'  => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'options' => ['raw' => ['language' => 'json']],
            ];
        }

        $name = strtoupper($method) . ' ' . $path;
        $entry = ['name' => $name, 'request' => $request];

        // Auto-capture the bearer token after a successful login/register so the
        // whole collection is usable without copy-pasting it by hand.
        if ($method === 'post' && in_array($path, ['/auth/login', '/auth/register'], true)) {
            $entry['event'] = [[
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => [
                        "var d = pm.response.json();",
                        "if (d && d.token) {",
                        "    pm.collectionVariables.set('token', d.token);",
                        "    console.log('Saved token to {{token}}.');",
                        "}",
                    ],
                ],
            ]];
        }

        $folders[$tag][] = $entry;
    }
}

// Drop empty folders, keep declared order.
$items = [];
foreach ($folders as $tag => $reqs) {
    if (! $reqs) continue;
    $items[] = ['name' => $tag, 'item' => $reqs];
}

$collection = [
    'info' => [
        'name'        => 'BIM API v2',
        '_postman_id' => '11111111-2222-4333-8444-555555555555',
        'description' => "Business In Map — v2 API.\nGenerated from docs/api/openapi-v2.yaml.\n\nSet {{base_url}} and, after logging in, paste the token into {{token}}. Public endpoints (auth, discovery, posts, jobs) ignore the bearer token.",
        'schema'      => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'auth' => [
        'type'   => 'bearer',
        'bearer' => [['key' => 'token', 'value' => '{{token}}', 'type' => 'string']],
    ],
    'variable' => [
        ['key' => 'base_url', 'value' => 'http://localhost/testing/public', 'type' => 'string'],
        ['key' => 'token', 'value' => '', 'type' => 'string'],
    ],
    'item' => $items,
];

$json = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
file_put_contents($root . '/docs/api/BIM-v2.postman_collection.json', $json . "\n");

// Report
$reqCount = 0;
foreach ($items as $f) $reqCount += count($f['item']);
echo "folders: " . count($items) . "\n";
echo "requests: " . $reqCount . "\n";
echo "bytes: " . strlen($json) . "\n";
