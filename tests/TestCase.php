<?php

declare(strict_types=1);

namespace Atlas\Agent\Tests;

use Atlas\Agent\Providers\AgentCliServiceProvider;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

/**
 * Class TestCase
 *
 * Provides the base Orchestra Testbench harness for exercising the Atlas Agent CLI package.
 */
abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $storagePath = $this->storagePath();
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0777, true);
        }

        $this->app->useStoragePath($storagePath);
        config()->set('atlas-agent-cli.sessions.path', storage_path('app/codex_sessions'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AgentCliServiceProvider::class,
        ];
    }

    private function storagePath(): string
    {
        return __DIR__.'/storage';
    }

    /**
     * @template T of MockInterface
     *
     * @param  T  $mock
     */
    protected function mockExpectation(MockInterface $mock, string $method): Expectation
    {
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive($method);

        return $expectation;
    }
}
