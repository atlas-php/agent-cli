<?php

declare(strict_types=1);

namespace Atlas\Agent\Providers;

use Atlas\Agent\Console\Commands\RunCodexSessionCommand;
use Atlas\Agent\Services\CodexCliSessionService;
use Atlas\Agent\Services\CommandRenderers\CodexCommandOutputRenderer;
use Atlas\Agent\Services\CommandRenderers\SessionCommandOutputRendererRegistry;
use Atlas\Agent\Services\SessionTranscripts\CodexSessionTranscriptParser;
use Atlas\Agent\Services\SessionTranscripts\SessionTranscriptService;
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

        $this->app->singleton(SessionCommandOutputRendererRegistry::class, function (): SessionCommandOutputRendererRegistry {
            return new SessionCommandOutputRendererRegistry([
                new CodexCommandOutputRenderer,
            ]);
        });

        $this->app->singleton(CodexCliSessionService::class, function (): CodexCliSessionService {
            $sessionsBasePath = (string) config('atlas-agent-cli.sessions.path', storage_path('app/sessions'));
            $sessionsPath = $this->providerDirectory($sessionsBasePath, 'codex');
            $workspacePath = config('atlas-agent-cli.workspace.path');
            $workspacePath = is_string($workspacePath) ? $workspacePath : null;

            return new CodexCliSessionService($sessionsPath, $workspacePath);
        });

        $this->app->singleton(SessionTranscriptService::class, function (): SessionTranscriptService {
            $sessionsBasePath = (string) config('atlas-agent-cli.sessions.path', storage_path('app/sessions'));

            return new SessionTranscriptService($sessionsBasePath, [
                new CodexSessionTranscriptParser,
            ]);
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
