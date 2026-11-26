<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'user',
        'email',
        'password',
        'turno_id',
        'turno',
        'estatus',
        'equipo_no_serie',
        'equipo_tipo',
        'equipo_tabla',
        'equipo_id_stellum',
        'equipo_marca',
        'equipo_ubicacion',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'estatus' => 'boolean',
        ];
    }

    public function turnoRelacion()
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    public function peticionesAutorizacion()
    {
        return $this->hasMany(PeticionAutorizacion::class, 'user_id');
    }

    public function huellas()
    {
        return $this->hasMany(Huella::class, 'user_id');
    }

    public function huellasActivas()
    {
        return $this->hasMany(Huella::class, 'user_id')->where('activo', true);
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class, 'user_id');
    }
}
