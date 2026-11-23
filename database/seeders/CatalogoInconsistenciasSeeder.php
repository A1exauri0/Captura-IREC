<?php

namespace Database\Seeders;

use App\Models\CatalogoInconsistencia;
use Illuminate\Database\Seeder;

class CatalogoInconsistenciasSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['codigo' => 'R01', 'motivo_rechazo' => 'Imagen ilegible o escaneo deficiente', 'accion_correctiva' => 'Solicitar reescaneo'],
            ['codigo' => 'R02', 'motivo_rechazo' => 'Página faltante', 'accion_correctiva' => 'Localizar e insertar página'],
            ['codigo' => 'R03', 'motivo_rechazo' => 'Página duplicada', 'accion_correctiva' => 'Eliminar duplicado'],
            ['codigo' => 'R04', 'motivo_rechazo' => 'Orientación o rotación incorrecta', 'accion_correctiva' => 'Rotar y enderezar'],
            ['codigo' => 'R05', 'motivo_rechazo' => 'Metadato indexado incorrecto', 'accion_correctiva' => 'Corregir y registrar en bitácora'],
            ['codigo' => 'R06', 'motivo_rechazo' => 'Campo obligatorio vacío', 'accion_correctiva' => 'Completar desde el documento'],
            ['codigo' => 'R07', 'motivo_rechazo' => 'Formato inválido de campo', 'accion_correctiva' => 'Ajustar a regla del manual'],
            ['codigo' => 'R08', 'motivo_rechazo' => 'Documento mal clasificado', 'accion_correctiva' => 'Reclasificar tipo documental'],
            ['codigo' => 'R09', 'motivo_rechazo' => 'PDF corrupto o sin capa de texto', 'accion_correctiva' => 'Regenerar archivo y aplicar OCR'],
        ];

        foreach ($items as $item) {
            CatalogoInconsistencia::updateOrCreate(
                ['codigo' => $item['codigo']],
                [
                    'motivo_rechazo'    => $item['motivo_rechazo'],
                    'accion_correctiva' => $item['accion_correctiva'],
                    'activo'            => true,
                ]
            );
        }
    }
}
