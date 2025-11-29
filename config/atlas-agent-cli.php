<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Codex Session Storage Directory
    |--------------------------------------------------------------------------
    |
    | This directory controls where the provider stores JSON Lines transcripts.
    | The Agent CLI automatically creates a "codex" sub-directory within the
    | configured path so multiple providers can share the same base directory.
    |
    */
    'sessions' => [
        'path' => env('ATLAS_AGENT_CLI_SESSION_PATH', storage_path('app/sessions')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Codex Workspace Directory
    |--------------------------------------------------------------------------
    |
    | Codex executes inside this directory so agent commands have access to the
    | appropriate project workspace without impacting the platform runtime. The
    | path should point to the workspace root that Codex should operate within.
    |
    */
    'workspace' => [
        'path' => env('ATLAS_AGENT_CLI_WORKSPACE_PATH', base_path()),
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
