<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Horizon Configuration
    |--------------------------------------------------------------------------
    | ERP-90 Phase 2: Named queue architecture with priority routing.
    | Queue hierarchy: critical > security > governance > agents >
    |                  billing > notifications > workflows > default
    */

    'name' => env('HORIZON_NAME', 'Dot.Agents Horizon'),

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],

    'environments' => [

        'production' => [
            // Critical priority — failures, security events, DIS alerts
            'critical-supervisor' => [
                'connection' => 'redis',
                'queue' => ['critical', 'security'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 10,
                'minProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'memory' => 256,
                'tries' => 5,
                'timeout' => 120,
            ],

            // Governance — audit logging, approvals, decision logs
            'governance-supervisor' => [
                'connection' => 'redis',
                'queue' => ['governance'],
                'balance' => 'auto',
                'maxProcesses' => 8,
                'minProcesses' => 2,
                'memory' => 256,
                'tries' => 5,
                'timeout' => 120,
            ],

            // Agent task execution — highest concurrency
            'agents-supervisor' => [
                'connection' => 'redis',
                'queue' => ['agents'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 20,
                'minProcesses' => 4,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
                'memory' => 512,
                'tries' => 3,
                'timeout' => 300,
            ],

            // Billing + invoicing
            'billing-supervisor' => [
                'connection' => 'redis',
                'queue' => ['billing'],
                'balance' => 'simple',
                'maxProcesses' => 4,
                'minProcesses' => 1,
                'memory' => 128,
                'tries' => 5,
                'timeout' => 60,
            ],

            // Notifications + emails + webhooks
            'notifications-supervisor' => [
                'connection' => 'redis',
                'queue' => ['notifications'],
                'balance' => 'auto',
                'maxProcesses' => 6,
                'minProcesses' => 2,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 60,
            ],

            // Workflow execution
            'workflows-supervisor' => [
                'connection' => 'redis',
                'queue' => ['workflows'],
                'balance' => 'auto',
                'maxProcesses' => 8,
                'minProcesses' => 2,
                'memory' => 256,
                'tries' => 3,
                'timeout' => 600,
            ],

            // Default catch-all
            'default-supervisor' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'simple',
                'maxProcesses' => 4,
                'minProcesses' => 1,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 60,
            ],

            // Dead-letter queue — monitors failed_jobs table, alerts on accumulation
            // Jobs land here after exhausting all retries on their primary queue.
            'dlq-supervisor' => [
                'connection' => 'redis',
                'queue' => ['failed'],
                'balance' => 'simple',
                'maxProcesses' => 1,
                'minProcesses' => 1,
                'memory' => 64,
                'tries' => 1,
                'timeout' => 30,
            ],
        ],

        'staging' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['critical', 'security', 'governance', 'agents', 'billing', 'notifications', 'workflows', 'default'],
                'balance' => 'simple',
                'maxProcesses' => 6,
                'minProcesses' => 1,
                'memory' => 256,
                'tries' => 3,
                'timeout' => 300,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['critical', 'security', 'governance', 'agents', 'billing', 'notifications', 'workflows', 'default'],
                'balance' => 'simple',
                'maxProcesses' => 3,
                'minProcesses' => 1,
                'memory' => 256,
                'tries' => 3,
                'timeout' => 300,
            ],
        ],

    ],

    // ── Metrics & Monitoring ─────────────────────────────────────────────────
    'trim' => [
        'recent' => 60,    // minutes to keep recent job history
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080, // 1 week
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'waits' => [
        'redis:critical' => 3,
        'redis:security' => 3,
        'redis:governance' => 10,
        'redis:agents' => 5,
        'redis:billing' => 15,
        'redis:notifications' => 15,
        'redis:workflows' => 10,
        'redis:default' => 60,
    ],

    // ── Access Control ───────────────────────────────────────────────────────
    'middleware' => ['auth', 'can:viewHorizon'],

    'admin_emails' => env('HORIZON_ADMIN_EMAILS', ''),

    'path' => env('HORIZON_PATH', 'horizon'),

    'domain' => env('HORIZON_DOMAIN', null),

    'use' => 'default',

    'fast_termination' => false,

    'memory_limit' => 64,
];
