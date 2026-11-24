<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reglas de Ruta Propuesta por Tipo de Archivo Documental
    |--------------------------------------------------------------------------
    |
    | Define las plantillas para calcular `ruta_propuesta` en base de datos
    | durante la captura (sin renombrar físicamente hasta aprobación).
    |
    | Placeholders disponibles:
    |   {base_destino}  - Carpeta raíz de entregables aprobados
    |   {delegacion}    - Nombre o clave de la delegación
    |   {tipo_sello}    - Subtipo de sello (si aplica)
    |   {anio}          - Año extraído del nombre o actual
    |   {lote}          - Nombre del lote de trabajo
    |   {directorio}    - Directorio o municipio de referencia
    |   {archivo_nuevo} - Nombre final propuesto (sin extensión)
    |
    */

    'base_destino' => env('PDF_DESTINO_BASE', ''),

    'reglas' => [
        'registros' => [
            'regla_captura'    => 'Regla1',
            'descripcion'      => 'Registros Públicos (Delegación / Año / Archivo)',
            'plantilla'        => '{base_destino}\\REGISTROS\\{delegacion}\\{anio}\\{archivo_nuevo}.pdf',
            'carpeta_duplicado'=> '{base_destino}\\REGISTROS\\{delegacion}\\{anio}\\DUPLICADOS\\{archivo_nuevo}.pdf',
            'carpeta_inconsistencia' => '{base_destino}\\REGISTROS\\{delegacion}\\INCONSISTENCIAS\\{codigo}\\{archivo_original}.pdf',
        ],

        'sellos' => [
            'regla_captura'    => 'Regla2',
            'descripcion'      => 'Sellos y Boletas de Inscripción (Delegación / Tipo Sello / Año / Archivo)',
            'plantilla'        => '{base_destino}\\SELLOS\\{delegacion}\\{tipo_sello}\\{anio}\\{archivo_nuevo}.pdf',
            'carpeta_duplicado'=> '{base_destino}\\SELLOS\\{delegacion}\\{tipo_sello}\\{anio}\\DUPLICADOS\\{archivo_nuevo}.pdf',
            'carpeta_inconsistencia' => '{base_destino}\\SELLOS\\{delegacion}\\INCONSISTENCIAS\\{codigo}\\{archivo_original}.pdf',
        ],

        'notarias' => [
            'regla_captura'    => 'ReglaNotarias',
            'descripcion'      => 'Protocolos de Notarías (Directorio / Lote / Archivo)',
            'plantilla'        => '{base_destino}\\NOTARIAS\\{directorio}\\{lote}\\{archivo_nuevo}.pdf',
            'carpeta_duplicado'=> '{base_destino}\\NOTARIAS\\{directorio}\\{lote}\\DUPLICADOS\\{archivo_nuevo}.pdf',
            'carpeta_inconsistencia' => '{base_destino}\\NOTARIAS\\{directorio}\\INCONSISTENCIAS\\{codigo}\\{archivo_original}.pdf',
        ],

        'catastro' => [
            'regla_captura'    => 'ReglaCatastro',
            'descripcion'      => 'Catastro Municipal (Directorio / Año / Archivo)',
            'plantilla'        => '{base_destino}\\CATASTRO\\{directorio}\\{anio}\\{archivo_nuevo}.pdf',
            'carpeta_duplicado'=> '{base_destino}\\CATASTRO\\{directorio}\\{anio}\\DUPLICADOS\\{archivo_nuevo}.pdf',
            'carpeta_inconsistencia' => '{base_destino}\\CATASTRO\\{directorio}\\INCONSISTENCIAS\\{codigo}\\{archivo_original}.pdf',
        ],
    ],

];
