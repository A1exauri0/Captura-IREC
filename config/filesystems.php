<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'irec' => [
            'driver' => 'local',
            'root' => '\\\\172.40.5.84\\irec',
            'throw' => false,
        ],

        'ssdirec' => [
            'driver' => 'local',
            'root' => '\\\\172.40.5.84\\ssdirec',
            'throw' => false,
        ],

        'cats' => [
            'driver' => 'local',
            'root' => '\\\\172.40.5.84\\cats',
            'throw' => false,
        ],

        'ssdcats' => [
            'driver' => 'local',
            'root' => '\\\\172.40.5.84\\ssdcats',
            'throw' => false,
        ],

        'rciv' => [
            'driver' => 'local',
            'root' => '\\\\172.40.5.84\\rciv',
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
