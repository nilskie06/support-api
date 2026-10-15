<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'username',
    'fullname',
    'email',
    'image',
    'password',
    'created_by',
    'status',
    'role',
    'last_device_token',
    'last_login_at',
    'theme_config',
    'layout_config',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'theme_config' => 'array',
            'layout_config' => 'array',
        ];
    }

    /**
     * Get the user who created this account.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * All entity assignments for this user (company, department, station, system).
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(UserAssigned::class, 'user_id');
    }

    /**
     * Company assignments for this user.
     */
    public function assignedCompanies(): HasMany
    {
        return $this->hasMany(UserAssigned::class, 'user_id')->where('reference', 'company');
    }

    /**
     * Department assignments for this user.
     */
    public function assignedDepartments(): HasMany
    {
        return $this->hasMany(UserAssigned::class, 'user_id')->where('reference', 'department');
    }

    /**
     * Station assignments for this user.
     */
    public function assignedStations(): HasMany
    {
        return $this->hasMany(UserAssigned::class, 'user_id')->where('reference', 'station');
    }

    /**
     * System assignments for this user.
     */
    public function assignedSystems(): HasMany
    {
        return $this->hasMany(UserAssigned::class, 'user_id')->where('reference', 'system');
    }

    /**
     * Menu access permissions for this user.
     */
    public function menuAccess(): HasMany
    {
        return $this->hasMany(UserMenuAcces::class, 'user_id');
    }
}
