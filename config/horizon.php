<?php

return [

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env('HORIZON_PREFIX', 'horizon:'),

    // Dashboard 'web' + admin gate (HorizonServiceProvider::gate) bilan himoyalangan.
    'middleware' => ['web'],

    'waits' => [
        'redis:default' => 60,
        'redis:ingestion' => 60,
        'redis:statistics' => 120,
        'redis:parser' => 120,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 64,

    'defaults' => [
        'supervisor' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 300,
            'nice' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supervisorlar (TZ 15: turli navbatlar uchun alohida supervisorlar)
    |--------------------------------------------------------------------------
    | - ingestion: parser'dan kelgan batch'larni qayta ishlash (tez, ko'p worker)
    | - statistics: og'ir, kam sonli mediana qayta hisoblash
    | - parser: backend ichidagi parser (scraping) joblari
    | - default: qolgan barcha (maintenance va h.k.)
    */
    'environments' => [

        'production' => [
            'supervisor-ingestion' => [
                'connection' => 'redis',
                'queue' => ['ingestion'],
                'balance' => 'auto',
                'maxProcesses' => 6,
                'tries' => 3,
                'timeout' => 300,
            ],
            'supervisor-statistics' => [
                'connection' => 'redis',
                'queue' => ['statistics'],
                'balance' => 'auto',
                'maxProcesses' => 2,
                'tries' => 1,
                'timeout' => 600,
            ],
            'supervisor-parser' => [
                'connection' => 'redis',
                'queue' => ['parser'],
                'balance' => 'auto',
                'maxProcesses' => 3,
                'tries' => 2,
                'timeout' => 600,
            ],
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default', 'maintenance'],
                'balance' => 'auto',
                'maxProcesses' => 3,
                'tries' => 3,
                'timeout' => 300,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['ingestion', 'statistics', 'parser', 'default', 'maintenance'],
                'balance' => 'auto',
                'maxProcesses' => 3,
                'tries' => 3,
                'timeout' => 300,
            ],
        ],
    ],
];
