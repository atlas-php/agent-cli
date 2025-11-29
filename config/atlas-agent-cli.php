<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Codex Session Storage Directory
    |--------------------------------------------------------------------------
    |
    | This directory stores the JSON Lines transcripts that are captured whenever
    | a Codex CLI session is executed in headless mode.
    |
    */
    'sessions' => [
        'path' => env('ATLAS_AGENT_CLI_SESSION_PATH', storage_path('app/codex_sessions')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Codex Runtime Options
    |--------------------------------------------------------------------------
    |
    | Control which model Codex should use whenever the `codex:session` command
    | forwards requests. This value may be overridden per invocation through the
    | command's --model option or by explicitly passing Codex CLI flags via args.
    |
    | Supported options today: gpt-5.1-codex-max, gpt-5.1-codex, gpt-5.1-codex-mini.
    |
    */
    'model' => env('ATLAS_AGENT_CLI_MODEL', 'gpt-5.1-codex-max'),
];
