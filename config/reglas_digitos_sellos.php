<?php

/*
|--------------------------------------------------------------------------
| Reglas de Cantidad de Dígitos por Campo de Sellos (Regla 2)
|--------------------------------------------------------------------------
|
| Define la longitud mínima y máxima permitida para cada uno de los 9 bloques
| del nombre de Sellos. Puede modificarse dinámicamente mediante el comando:
|   php artisan sellos:ajustar-digitos --campo=libro --digitos=4
|
*/

$defaults = [
    'delegacion'      => ['idx' => 0, 'label' => '1. Delegación',      'min' => 2, 'max' => 2,  'numerico' => true],
    'seccion'         => ['idx' => 1, 'label' => '2. Sección',         'min' => 2, 'max' => 3,  'numerico' => true],
    'libro'           => ['idx' => 2, 'label' => '3. Libro',           'min' => 2, 'max' => 6,  'numerico' => true],
    'tomo'            => ['idx' => 3, 'label' => '4. Tomo',            'min' => 1, 'max' => 30, 'numerico' => false],
    'volumen'         => ['idx' => 4, 'label' => '5. Volumen',         'min' => 2, 'max' => 3,  'numerico' => true],
    'fraccionamiento' => ['idx' => 5, 'label' => '6. Fraccionamiento', 'min' => 1, 'max' => 55, 'numerico' => false],
    'anio'            => ['idx' => 6, 'label' => '7. Año',             'min' => 4, 'max' => 4,  'numerico' => true],
    'inscripcion'     => ['idx' => 7, 'label' => '8. Inscripción',     'min' => 4, 'max' => 4,  'numerico' => true],
    'bis'             => ['idx' => 8, 'label' => '9. Bis',             'min' => 2, 'max' => 3,  'numerico' => false],
];

$overridePath = storage_path('app/reglas_digitos_sellos.json');
if (is_file($overridePath)) {
    $custom = json_decode((string) @file_get_contents($overridePath), true);
    if (is_array($custom)) {
        foreach ($custom as $k => $vals) {
            if (isset($defaults[$k]) && is_array($vals)) {
                $defaults[$k] = array_merge($defaults[$k], $vals);
            }
        }
    }
}

return $defaults;
