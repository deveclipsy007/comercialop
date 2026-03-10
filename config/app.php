<?php

return [
    'name'    => env('APP_NAME', 'Operon Intelligence'),
    'env'     => env('APP_ENV', 'local'),
    'debug'   => env('APP_DEBUG', true),
    'url'     => env('APP_URL', 'http://localhost:8000'),
    'secret'  => env('APP_SECRET', 'change-this'),
    'timezone' => 'America/Sao_Paulo',

    'version' => '1.0.0',

    // Módulos do sistema (nav sidebar)
    'modules' => [
        'nexus'    => ['label' => 'Nexus',     'icon' => 'grid_view',       'path' => '/'],
        'vault'    => ['label' => 'Vault',      'icon' => 'table_view',      'path' => '/vault'],
        'atlas'    => ['label' => 'Atlas',      'icon' => 'map',             'path' => '/atlas'],
        'hunter'   => ['label' => 'Hunter',     'icon' => 'track_changes',   'path' => '/hunter'],
        'genesis'  => ['label' => 'Genesis',    'icon' => 'upload',          'path' => '/genesis'],
        'agenda'   => ['label' => 'Agenda',     'icon' => 'calendar_today',  'path' => '/agenda'],
        'followup' => ['label' => 'Follow-up',  'icon' => 'event_available', 'path' => '/follow-up'],
        'spin'     => ['label' => 'SPIN Hub',   'icon' => 'adjust',          'path' => '/spin'],
        'admin'    => ['label' => 'Admin',      'icon' => 'shield',          'path' => '/admin'],
    ],
];
