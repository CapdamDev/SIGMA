<?php

namespace App\Models;

use App\Enums\Rol;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'rol', 'activo'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'rol' => 'consulta',
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => Rol::class,
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // El primer usuario del sistema (p. ej. creado con make:filament-user) es administrador.
        static::creating(function (User $user) {
            if (static::query()->doesntExist()) {
                $user->rol = Rol::Admin;
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->activo;
    }

    public function esAdmin(): bool
    {
        return $this->rol === Rol::Admin;
    }

    public function puedeCapturar(): bool
    {
        return in_array($this->rol, [Rol::Admin, Rol::Capturista], true);
    }
}
