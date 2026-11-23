<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuracion de Rutas de Revision y Captura de PDFs (Proyecto Antiguo Stellum)
    |--------------------------------------------------------------------------
    |
    | Rutas organizadas por tipo de archivo y entrega. Esta estructura es
    | extensible: para agregar nuevos tipos de archivo o nuevas entregas en el
    | futuro, basta con anadir una entrada al arreglo 'fuentes' o 'tipos'.
    |
    */

    'servidor_red' => env('PDF_NETWORK_SERVER', '\\\\172.40.5.84\\ssdirec'),

    'tipos_documento' => [
        'registros' => [
            'nombre' => 'Registros',
            'icono'  => 'fa-file-signature',
            'color'  => 'indigo',
            'tabla'  => 'auditoria_registros',
            'regla'  => 'Regla1',
        ],
        'sellos' => [
            'nombre' => 'Sellos y Boletas',
            'icono'  => 'fa-stamp',
            'color'  => 'emerald',
            'tabla'  => 'auditoria_sellos',
            'regla'  => 'Regla2',
        ],
        'catastro' => [
            'nombre' => 'Catastro',
            'icono'  => 'fa-map-location-dot',
            'color'  => 'amber',
            'tabla'  => 'auditoria_catastro',
            'regla'  => 'ReglaCatastro',
        ],
        'notarias' => [
            'nombre' => 'Notarías',
            'icono'  => 'fa-landmark',
            'color'  => 'sky',
            'tabla'  => 'auditoria_notarias',
            'regla'  => 'ReglaNotarias',
        ],
    ],

    'rutas_por_tipo' => [
        'registros' => [
            [
                'id'      => 'entrega_1_registros',
                'entrega' => 'ENTREGA 1',
                'nombre'  => 'ENTREGA 1 - 1 ENTREGA REGISTROS',
                'ruta'    => '\\\\172.40.5.84\\ssdirec\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 1\\1 ENTREGA REGISTROS',
            ],
            [
                'id'      => 'entrega_2_registros',
                'entrega' => 'ENTREGA 2',
                'nombre'  => 'ENTREGA 2 - ENTREGA REGISTROS',
                'ruta'    => '\\\\172.40.5.84\\ssdirec\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 2\\ENTREGA REGISTROS',
            ],
            [
                'id'      => 'entrega_4_registros',
                'entrega' => 'ENTREGA 4',
                'nombre'  => 'ENTREGA 4 - REGISTROS',
                'ruta'    => '\\\\172.40.5.84\\ssdirec\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 4\\REGISTROS',
            ],
        ],

        'sellos' => [
            [
                'id'      => 'entrega_1_sellos',
                'entrega' => 'ENTREGA 1',
                'nombre'  => 'ENTREGA 1 - 2 ENTREGA SELLOS',
                'ruta'    => '\\\\172.40.5.84\\ssdirec\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 1\\2 ENTREGA SELLOS',
            ],
            [
                'id'      => 'entrega_3_sellos',
                'entrega' => 'ENTREGA 3',
                'nombre'  => 'ENTREGA 3 - SELLOS',
                'ruta'    => '\\\\172.40.5.84\\ssdirec\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 3\\SELLOS',
            ],
            [
                'id'      => 'entrega_4_sellos_boletas',
                'entrega' => 'ENTREGA 4',
                'nombre'  => 'ENTREGA 4 - SELLOS BOLETAS DE INSCRIPCIÓN',
                'ruta'    => '\\\\172.40.5.84\\ssdirec\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 4\\SELLOS BOLETAS DE INSCRIPCIÓN',
            ],
        ],

        'catastro' => [
            [
                'id'      => 'entrega_1_catastro',
                'entrega' => 'ENTREGA 1',
                'nombre'  => 'ENTREGA 1 - 3 ENTREGA CATASTRO',
                'ruta'    => '\\\\172.40.5.84\\ssdirec\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 1\\3 ENTREGA CATASTRO',
            ],
        ],

        'notarias' => [
            [
                'id'      => 'entrega_3_notarias',
                'entrega' => 'ENTREGA 3',
                'nombre'  => 'ENTREGA 3 - NOTARÍAS',
                'ruta'    => '\\\\172.40.5.84\\ssdirec\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 3\\NOTARÍAS',
            ],
            [
                'id'      => 'entrega_4_notarias',
                'entrega' => 'ENTREGA 4',
                'nombre'  => 'ENTREGA 4 - NOTARIAS',
                'ruta'    => '\\\\172.40.5.84\\ssdirec\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 4\\NOTARIAS',
            ],
            [
                'id'      => 'entrega_5_notarias',
                'entrega' => 'ENTREGA 5',
                'nombre'  => 'ENTREGA 5 - NOTARIAS',
                'ruta'    => '\\\\172.40.5.84\\ssdirec\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 5\\NOTARIAS',
            ],
        ],
    ],

    // Discos heredados de compatibilidad con el proyecto antiguo de Stellum
    'discos_stellum' => [
        'irec'    => '\\\\172.40.5.84\\irec',
        'ssdirec' => '\\\\172.40.5.84\\ssdirec',
        'cats'    => '\\\\172.40.5.84\\cats',
        'ssdcats' => '\\\\172.40.5.84\\ssdcats',
        'rciv'    => '\\\\172.40.5.84\\rciv',
    ],

];
