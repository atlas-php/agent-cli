<?php

declare(strict_types=1);

namespace Atlas\Agent\Providers;

use Atlas\Agent\Console\Commands\RunCodexSessionCommand;
use Atlas\Agent\Services\CodexCliSessionService;
use Atlas\Core\Providers\PackageServiceProvider;

/**
 * Class AgentCliServiceProvider
 *
 * Wires the Codex CLI session service and registers the artisan command for streaming Codex runs.
 */
class AgentCliServiceProvider extends PackageServiceProvider
{
    protected string $packageBasePath = __DIR__.'/../..';

    public function register(): void
    {
        $this->mergeConfigFrom(
            $this->packageConfigPath('atlas-agent-cli.php'),
            'atlas-agent-cli'
        );

        $this->app->singleton(CodexCliSessionService::class, function (): CodexCliSessionService {
            $sessionsBasePath = (string) config('atlas-agent-cli.sessions.path', storage_path('app/sessions'));
            $sessionsPath = $this->providerDirectory($sessionsBasePath, 'codex');
            $workspacePath = config('atlas-agent-cli.workspace.path');
            $workspacePath = is_string($workspacePath) ? $workspacePath : null;

            return new CodexCliSessionService($sessionsPath, $workspacePath);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->packageConfigPath('atlas-agent-cli.php') => config_path('atlas-agent-cli.php'),
            ], $this->tags()->config());

            $this->notifyPendingInstallSteps(
                'Atlas Agent CLI',
                'atlas-agent-cli.php',
                $this->tags()->config()
            );

            $this->commands([
                RunCodexSessionCommand::class,
            ]);
        }
    }

    protected function packageSlug(): string
    {
        return 'atlas agent cli';
    }

    private function providerDirectory(string $basePath, string $provider): string
    {
        $trimmedBase = rtrim($basePath, DIRECTORY_SEPARATOR);
        $trimmedProvider = trim($provider, DIRECTORY_SEPARATOR);

        return $trimmedBase.DIRECTORY_SEPARATOR.$trimmedProvider;
    }
}
