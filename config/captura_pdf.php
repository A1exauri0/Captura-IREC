<?php

$hostRed = trim((string) env('PDF_NETWORK_HOST', ''), '\\/');
$prefijoUnc = $hostRed !== '' ? '\\\\' . $hostRed : '';
$ssdirecBase = rtrim((string) env('PDF_NETWORK_SERVER', $prefijoUnc ? ($prefijoUnc . '\\ssdirec') : ''), '\\/');

return [

    /*
    |--------------------------------------------------------------------------
    | Configuración de Rutas de Revisión y Captura de PDFs (vía .env)
    |--------------------------------------------------------------------------
    */

    'host_red'     => $hostRed,
    'servidor_red' => $ssdirecBase,

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
                'ruta'    => $ssdirecBase . '\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 1\\1 ENTREGA REGISTROS',
            ],
            [
                'id'      => 'entrega_2_registros',
                'entrega' => 'ENTREGA 2',
                'nombre'  => 'ENTREGA 2 - ENTREGA REGISTROS',
                'ruta'    => $ssdirecBase . '\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 2\\ENTREGA REGISTROS',
            ],
            [
                'id'      => 'entrega_4_registros',
                'entrega' => 'ENTREGA 4',
                'nombre'  => 'ENTREGA 4 - REGISTROS',
                'ruta'    => $ssdirecBase . '\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 4\\REGISTROS',
            ],
        ],

        'sellos' => [
            [
                'id'      => 'entrega_1_sellos',
                'entrega' => 'ENTREGA 1',
                'nombre'  => 'ENTREGA 1 - 2 ENTREGA SELLOS',
                'ruta'    => $ssdirecBase . '\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 1\\2 ENTREGA SELLOS',
            ],
            [
                'id'      => 'entrega_3_sellos',
                'entrega' => 'ENTREGA 3',
                'nombre'  => 'ENTREGA 3 - SELLOS',
                'ruta'    => $ssdirecBase . '\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 3\\SELLOS',
            ],
            [
                'id'      => 'entrega_4_sellos_boletas',
                'entrega' => 'ENTREGA 4',
                'nombre'  => 'ENTREGA 4 - SELLOS BOLETAS DE INSCRIPCIÓN',
                'ruta'    => $ssdirecBase . '\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 4\\SELLOS BOLETAS DE INSCRIPCIÓN',
            ],
        ],

        'catastro' => [
            [
                'id'      => 'entrega_1_catastro',
                'entrega' => 'ENTREGA 1',
                'nombre'  => 'ENTREGA 1 - 3 ENTREGA CATASTRO',
                'ruta'    => $ssdirecBase . '\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 1\\3 ENTREGA CATASTRO',
            ],
        ],

        'notarias' => [
            [
                'id'      => 'entrega_3_notarias',
                'entrega' => 'ENTREGA 3',
                'nombre'  => 'ENTREGA 3 - NOTARÍAS',
                'ruta'    => $ssdirecBase . '\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 3\\NOTARÍAS',
            ],
            [
                'id'      => 'entrega_4_notarias',
                'entrega' => 'ENTREGA 4',
                'nombre'  => 'ENTREGA 4 - NOTARIAS',
                'ruta'    => $ssdirecBase . '\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 4\\NOTARIAS',
            ],
            [
                'id'      => 'entrega_5_notarias',
                'entrega' => 'ENTREGA 5',
                'nombre'  => 'ENTREGA 5 - NOTARIAS',
                'ruta'    => $ssdirecBase . '\\ENTREGABLES PROCESADOS FINANZAS\\ENTREGA 5\\NOTARIAS',
            ],
        ],
    ],

    // Discos de red configurados dinámicamente desde PDF_NETWORK_HOST en .env
    'discos_stellum' => [
        'irec'    => $prefijoUnc ? ($prefijoUnc . '\\irec') : '',
        'ssdirec' => $ssdirecBase,
        'cats'    => $prefijoUnc ? ($prefijoUnc . '\\cats') : '',
        'ssdcats' => $prefijoUnc ? ($prefijoUnc . '\\ssdcats') : '',
        'rciv'    => $prefijoUnc ? ($prefijoUnc . '\\rciv') : '',
    ],

];
