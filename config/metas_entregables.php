<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Metas Fijas de Archivos Entregables (indice_archivos_pdfs)
    |--------------------------------------------------------------------------
    | Totales definitivos tomados de indice_archivos_pdfs (1,671,607 totales;
    | 551,477 eliminados; 1,120,130 entregables de Sellos).
    | Al ser una meta fija que no cambia, el Dashboard calcula avance y
    | faltantes de forma instantánea sin escanear 1.6M de filas.
    */

    'total_indice'      => 1671607,
    'total_eliminados'  => 551477,
    'total_entregables' => 1120130,
    'total_paginas'     => 22079039,

    'por_delegacion' => [
        ['nombre' => 'Tuxtla Gutiérrez',    'meta' => 468759, 'paginas' => 8696225],
        ['nombre' => 'Tapachula',           'meta' => 121629, 'paginas' => 4229695],
        ['nombre' => 'Comitán',             'meta' => 103950, 'paginas' => 1428984],
        ['nombre' => 'Huixtla',             'meta' => 59502,  'paginas' => 1038829],
        ['nombre' => 'Catazajá',            'meta' => 41913,  'paginas' => 911392],
        ['nombre' => 'Ocosingo',            'meta' => 41363,  'paginas' => 908601],
        ['nombre' => 'Pichucalco',          'meta' => 38911,  'paginas' => 1086202],
        ['nombre' => 'Cintalapa',           'meta' => 36003,  'paginas' => 436210],
        ['nombre' => 'Salto de Agua',       'meta' => 30290,  'paginas' => 297778],
        ['nombre' => 'Venustiano Carranza', 'meta' => 28642,  'paginas' => 443145],
        ['nombre' => 'Tonalá',              'meta' => 27179,  'paginas' => 394008],
        ['nombre' => 'Yajalón',             'meta' => 26927,  'paginas' => 407470],
        ['nombre' => 'Acapetahua',          'meta' => 24867,  'paginas' => 486806],
        ['nombre' => 'San Cristóbal',       'meta' => 24617,  'paginas' => 367111],
        ['nombre' => 'Copainalá',           'meta' => 19440,  'paginas' => 518725],
        ['nombre' => 'Bochil',              'meta' => 15818,  'paginas' => 248334],
        ['nombre' => 'Motozintla',          'meta' => 9580,   'paginas' => 163218],
        ['nombre' => 'Villaflores',         'meta' => 740,    'paginas' => 16306],
    ],
];
