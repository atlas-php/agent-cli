<?php

declare(strict_types=1);

namespace Atlas\Agent\Tests;

use Atlas\Agent\Providers\AgentCliServiceProvider;
use Atlas\Agent\Tests\Support\HasMockeryExpectations;
use Mockery;
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
        config()->set('atlas-agent-cli.sessions.path', storage_path('app/sessions'));

        $workspacePath = $this->workspacePath();
        if (! is_dir($workspacePath)) {
            mkdir($workspacePath, 0777, true);
        }

        config()->set('atlas-agent-cli.workspace.path', $workspacePath);
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

    protected function workspacePath(): string
    {
        return __DIR__.'/workspace';
    }

    protected function codexSessionsDirectory(): string
    {
        $base = (string) config('atlas-agent-cli.sessions.path');

        return rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'codex';
    }

    /**
     * @template TMock of MockInterface
     *
     * @param  TMock  $mock
     * @return \Mockery\ExpectationInterface&HasMockeryExpectations
     */
    protected function mockExpectation(MockInterface $mock, string $method)
    {
        /** @var \Mockery\ExpectationInterface&HasMockeryExpectations $expectation */
        $expectation = $mock->shouldReceive($method);

        return $expectation;
    }
}
