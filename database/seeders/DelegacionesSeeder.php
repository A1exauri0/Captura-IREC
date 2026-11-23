<?php

namespace Database\Seeders;

use App\Models\Delegaciones;
use Illuminate\Database\Seeder;

class DelegacionesSeeder extends Seeder
{
    public function run()
    {
        Delegaciones::insert([

            [
                "id" => 1,
                "nombre" => "TUXTLA GUTIÉRREZ",
                "clave" => "01",
                "estatus" => 1
            ],
            [
                "id" => 2,
                "nombre" => "SAN CRISTOBAL",
                "clave" => "02",
                "estatus" => 1
            ],
            [
                "id" => 3,
                "nombre" => "VENUSTIANO CARRANZA",
                "clave" => "03",
                "estatus" => 1
            ],
            [
                "id" => 4,
                "nombre" => "COMITAN",
                "clave" => "04",
                "estatus" => 1
            ],
            [
                "id" => 5,
                "nombre" => "BOCHIL",
                "clave" => "05",
                "estatus" => 1
            ],
            [
                "id" => 6,
                "nombre" => "COPAINALA",
                "clave" => "06",
                "estatus" => 1
            ],
            [
                "id" => 7,
                "nombre" => "TONALA",
                "clave" => "07",
                "estatus" => 1
            ],
            [
                "id" => 8,
                "nombre" => "TAPACHULA",
                "clave" => "08",
                "estatus" => 1
            ],
            [
                "id" => 9,
                "nombre" => "CHIAPA DE CORZO",
                "clave" => "09",
                "estatus" => 1
            ],
            [
                "id" => 10,
                "nombre" => "HUIXTLA",
                "clave" => "10",
                "estatus" => 1
            ],
            [
                "id" => 11,
                "nombre" => "MOTOZINTLA",
                "clave" => "11",
                "estatus" => 1
            ],
            [
                "id" => 12,
                "nombre" => "CINTALAPA",
                "clave" => "12",
                "estatus" => 1
            ],
            [
                "id" => 13,
                "nombre" => "ACAPETAHUA",
                "clave" => "13",
                "estatus" => 1
            ],
            [
                "id" => 14,
                "nombre" => "PICHUCALCO",
                "clave" => "14",
                "estatus" => 1
            ],
            [
                "id" => 15,
                "nombre" => "VILLAFLORES",
                "clave" => "15",
                "estatus" => 1
            ],
            [
                "id" => 16,
                "nombre" => "SALTO DE AGUA",
                "clave" => "16",
                "estatus" => 1
            ],
            [
                "id" => 17,
                "nombre" => "CATAZAJA",
                "clave" => "17",
                "estatus" => 1
            ],
            [
                "id" => 18,
                "nombre" => "OCOSINGO",
                "clave" => "18",
                "estatus" => 1
            ],
            [
                "id" => 19,
                "nombre" => "YAJALON",
                "clave" => "19",
                "estatus" => 1
            ]

        ]);
    }
}