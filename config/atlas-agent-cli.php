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
        'path' => storage_path('app/sessions'),
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
        'path' => base_path(),
    ],

    /*
     |--------------------------------------------------------------------------
     | Provider Defaults
     |--------------------------------------------------------------------------
     |
     | Configure defaults per provider so the Codex CLI receives the right model,
     | reasoning mode, and approval policy on every run unless explicitly
     | overridden via command options or raw CLI flags.
     |
     */
    'providers' => [
        'codex' => [
            'model' => 'gpt-5.1-codex-max',
            'reasoning' => 'medium',
            'approval' => 'never',
        ],
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
