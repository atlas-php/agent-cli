<?php

declare(strict_types=1);

namespace Atlas\Agent\Providers;

use Atlas\Agent\Console\Commands\RunCodexSessionCommand;
use Atlas\Agent\Services\CodexCliSessionService;
use Illuminate\Support\ServiceProvider;

/**
 * Class AgentCliServiceProvider
 *
 * Wires the Codex CLI session service and registers the artisan command for streaming Codex runs.
 */
class AgentCliServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CodexCliSessionService::class, static function (): CodexCliSessionService {
            return new CodexCliSessionService();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunCodexSessionCommand::class,
            ]);
        }
    }
}
