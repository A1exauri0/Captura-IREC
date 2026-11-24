<?php

$pdfHost = trim((string) env('PDF_NETWORK_HOST', ''), '\\/');
$uncPrefix = $pdfHost !== '' ? '\\\\' . $pdfHost : storage_path('app/pdf_network');

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
            'root' => $uncPrefix . DIRECTORY_SEPARATOR . 'irec',
            'throw' => false,
        ],

        'ssdirec' => [
            'driver' => 'local',
            'root' => env('PDF_NETWORK_SERVER', $uncPrefix . DIRECTORY_SEPARATOR . 'ssdirec'),
            'throw' => false,
        ],

        'cats' => [
            'driver' => 'local',
            'root' => $uncPrefix . DIRECTORY_SEPARATOR . 'cats',
            'throw' => false,
        ],

        'ssdcats' => [
            'driver' => 'local',
            'root' => $uncPrefix . DIRECTORY_SEPARATOR . 'ssdcats',
            'throw' => false,
        ],

        'rciv' => [
            'driver' => 'local',
            'root' => $uncPrefix . DIRECTORY_SEPARATOR . 'rciv',
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
