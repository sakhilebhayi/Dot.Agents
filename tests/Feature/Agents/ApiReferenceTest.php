<?php

namespace Tests\Feature\Agents;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/** The reference stays honest: every route is in it, and nothing is in it that is not a route. */
class ApiReferenceTest extends TestCase
{
    public function test_the_reference_and_the_routes_match_exactly(): void
    {
        $spec = Yaml::parseFile(base_path('docs/openapi.yaml'));

        $documented = collect($spec['paths'])
            ->flatMap(fn (array $path, string $uri) => collect($path)->keys()->map(fn (string $verb) => strtoupper($verb).' '.$uri))
            ->sort()->values()->all();

        $routes = collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/'))
            ->flatMap(fn (Route $route) => collect($route->methods())->reject(fn (string $m) => $m === 'HEAD')->map(fn (string $m) => $m.' '.substr($route->uri(), 3)))
            ->sort()->values()->all();

        $this->assertSame($routes, $documented);
    }
}
