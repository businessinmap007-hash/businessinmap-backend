<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * docs/api/openapi-v2.yaml is hand-maintained, so the rule "update it alongside
 * any /api/v2 route change" only holds if something checks. This turns that rule
 * into a failing test: add a route without documenting it and the suite says so.
 * Touches no database.
 */
class OpenApiSpecCoverageTest extends TestCase
{
    private const SPEC = 'docs/api/openapi-v2.yaml';

    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();

        $path = base_path(self::SPEC);

        if (! is_file($path)) {
            $this->fail(self::SPEC . ' is missing.');
        }

        $this->spec = Yaml::parseFile($path);
    }

    /** Every /api/v2 route path, normalised to how the spec writes it. */
    private function routePaths(): array
    {
        $paths = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v2')) {
                continue;
            }

            $path = '/' . ltrim(preg_replace('#^api/v2#', '', $uri), '/');
            $paths[rtrim($path, '/') ?: '/'] = true;
        }

        return array_keys($paths);
    }

    public function test_spec_parses_and_declares_the_basics(): void
    {
        $this->assertSame('3.0.3', $this->spec['openapi']);
        $this->assertNotEmpty($this->spec['paths']);
        $this->assertNotEmpty($this->spec['tags']);
    }

    public function test_every_api_route_is_documented(): void
    {
        $documented = array_keys($this->spec['paths']);
        $undocumented = array_values(array_diff($this->routePaths(), $documented));

        $this->assertSame(
            [],
            $undocumented,
            "These /api/v2 routes are not in " . self::SPEC . " — document them alongside the route:\n  "
                . implode("\n  ", $undocumented)
        );
    }

    public function test_spec_documents_no_route_that_does_not_exist(): void
    {
        $routes = $this->routePaths();
        $phantom = array_values(array_diff(array_keys($this->spec['paths']), $routes));

        $this->assertSame(
            [],
            $phantom,
            "These paths are documented but no /api/v2 route serves them:\n  " . implode("\n  ", $phantom)
        );
    }

    public function test_every_tag_used_is_declared(): void
    {
        $declared = array_column($this->spec['tags'], 'name');
        $used = [];

        foreach ($this->spec['paths'] as $operations) {
            foreach ($operations as $method => $operation) {
                if (! is_array($operation) || ! isset($operation['tags'])) {
                    continue;
                }

                foreach ($operation['tags'] as $tag) {
                    $used[$tag] = true;
                }
            }
        }

        $this->assertSame([], array_values(array_diff(array_keys($used), $declared)), 'tags used on an operation but never declared');
    }

    public function test_every_internal_ref_resolves(): void
    {
        // JSON_UNESCAPED_SLASHES matters: by default json_encode writes the
        // ref as "#\/components\/..." and the pattern below would never match,
        // making this check silently vacuous.
        preg_match_all(
            '#"\$ref":"\#/components/([a-zA-Z]+)/([A-Za-z0-9_]+)"#',
            json_encode($this->spec, JSON_UNESCAPED_SLASHES),
            $matches,
            PREG_SET_ORDER
        );

        $this->assertNotEmpty($matches, 'expected the spec to use $ref');

        $broken = [];

        foreach ($matches as [, $section, $name]) {
            if (! isset($this->spec['components'][$section][$name])) {
                $broken[] = "#/components/{$section}/{$name}";
            }
        }

        $this->assertSame([], array_values(array_unique($broken)), 'dangling $ref targets');
    }
}
