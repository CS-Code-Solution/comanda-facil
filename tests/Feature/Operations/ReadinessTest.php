<?php

namespace Tests\Feature\Operations;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    public function test_database_outage_returns_minimal_503_without_touching_database_sessions_or_cache(): void
    {
        config(['session.driver' => 'database', 'cache.default' => 'database']);
        // ResponseFactory may construct the session handler, but must never query it.
        DB::shouldReceive('connection')->with(null)->andReturn(Mockery::mock(Connection::class));
        DB::shouldReceive('select')->once()->with('select 1')->andThrow(new \RuntimeException('Sensitive internal detail'));
        $response = $this->getJson('/ready');
        $response->assertStatus(503)->assertExactJson(['ready' => false]);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_healthy_database_returns_minimal_response_without_session_cookie(): void
    {
        DB::shouldReceive('select')->once()->with('select 1')->andReturn([1]);
        $response = $this->getJson('/ready');
        $response->assertOk()->assertExactJson(['ready' => true]);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertSame([], $response->headers->getCookies());
    }
}
