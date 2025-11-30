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
    | Control which model each provider should use whenever the `codex:session`
    | command forwards requests. Values are keyed by provider so you can set
    | defaults for multiple providers. Each value may be overridden per
    | invocation through the command's --model option or by explicitly passing
    | Codex CLI flags via args.
    |
    | Supported options today: gpt-5.1-codex-max, gpt-5.1-codex, gpt-5.1-codex-mini.
    |
    */
    'model' => [
        'codex' => env('ATLAS_AGENT_CLI_MODEL_CODEX', 'gpt-5.1-codex-max'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Codex Reasoning Strategy
    |--------------------------------------------------------------------------
    |
    | Choose the reasoning mode Codex should apply for each provider. Set this
    | to a supported value such as "medium" or "deep" to match the selected
    | Codex model. The --reasoning option overrides this per invocation.
    |
    */
    'reasoning' => [
        'codex' => env('ATLAS_AGENT_CLI_REASONING_CODEX', 'medium'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Task and Instruction Format
    |--------------------------------------------------------------------------
    |
    | Optional template that combines the user task and any additional system
    | instructions before they are forwarded to Codex. Use {TASK} and
    | {INSTRUCTIONS} placeholders to control how the final request is shaped.
    | The raw template and rendered output are both logged in the workspace
    | event so you can see exactly what Codex receives.
    |
    */
    'template' => [
        'task' => 'Task: {TASK}',
        'instructions' => 'Instructions: {INSTRUCTIONS}',
    ],
];
