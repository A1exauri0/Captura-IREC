<?php

namespace Database\Seeders;

use App\Models\Anio;
use Illuminate\Database\Seeder;

class AnioSeeder extends Seeder
{
    public function run()
    {
        Anio::insert([

            array(
                "id" => 1,
                "nombre" => "2011",
                "estatus" => 1
            ),
            array(
                "id" => 2,
                "nombre" => "2012",
                "estatus" => 1
            ),
            array(
                "id" => 3,
                "nombre" => "2013",
                "estatus" => 1
            ),
            array(
                "id" => 4,
                "nombre" => "2014",
                "estatus" => 1
            ),
            array(
                "id" => 5,
                "nombre" => "2015",
                "estatus" => 1
            ),
            array(
                "id" => 6,
                "nombre" => "2016",
                "estatus" => 1
            ),
            array(
                "id" => 7,
                "nombre" => "2017",
                "estatus" => 1
            ),
            array(
                "id" => 8,
                "nombre" => "2018",
                "estatus" => 1
            ),
            array(
                "id" => 9,
                "nombre" => "2019",
                "estatus" => 1
            ),
            array(
                "id" => 10,
                "nombre" => "2020",
                "estatus" => 1
            ),
            array(
                "id" => 11,
                "nombre" => "2021",
                "estatus" => 1
            ),
            array(
                "id" => 12,
                "nombre" => "2022",
                "estatus" => 1
            ),
            array(
                "id" => 13,
                "nombre" => "2023",
                "estatus" => 1
            ),
            array(
                "id" => 14,
                "nombre" => "2024",
                "estatus" => 1
            ),
            array(
                "id" => 15,
                "nombre" => "2025",
                "estatus" => 1
            ),
            array(
                "id" => 16,
                "nombre" => "2026",
                "estatus" => 1
            )
        ]);
    }
}
