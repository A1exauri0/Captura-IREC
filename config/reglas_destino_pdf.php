<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reglas de Ruta Propuesta por Tipo de Archivo Documental
    |--------------------------------------------------------------------------
    |
    | La ruta propuesta conserva la misma ubicación de entregables en `ruta_origen`,
    | actualizando únicamente la carpeta del documento (hasta el segmento de año)
    | y el nombre final del archivo cuando cambia durante la captura.
    | `ruta_destino` permanece vacía hasta que los cambios se apliquen físicamente.
    |
    */

    'reglas' => [
        'registros' => [
            'regla_captura' => 'Regla1',
            'descripcion'   => 'Registros Públicos (conserva ubicación de entregables)',
        ],

        'sellos' => [
            'regla_captura' => 'Regla2',
            'descripcion'   => 'Sellos (conserva ruta base de entregables, carpeta hasta el año + archivo)',
        ],

        'notarias' => [
            'regla_captura' => 'ReglaNotarias',
            'descripcion'   => 'Protocolos de Notarías (conserva ubicación de entregables)',
        ],

        'catastro' => [
            'regla_captura' => 'ReglaCatastro',
            'descripcion'   => 'Catastro Municipal (conserva ubicación de entregables)',
        ],
    ],

];
