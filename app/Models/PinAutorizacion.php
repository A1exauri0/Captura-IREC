<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PinAutorizacion extends Model
{
    protected $table = 'pines_autorizacion';

    protected $fillable = [
        'pin',
        'usado',
        'usado_por',
        'usado_en',
    ];

    protected $casts = [
        'usado' => 'boolean',
        'usado_en' => 'datetime',
    ];

    public static function obtenerPinActivo(): string
    {
        $registro = self::where('usado', false)->orderBy('id', 'desc')->first();
        if (!$registro) {
            $registro = self::create([
                'pin' => str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
                'usado' => false,
            ]);
        }
        return $registro->pin;
    }

    public static function regenerarPin(): string
    {
        self::where('usado', false)->update(['usado' => true, 'usado_en' => now()]);
        $nuevo = self::create([
            'pin' => str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
            'usado' => false,
        ]);
        return $nuevo->pin;
    }
}
