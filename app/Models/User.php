<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Role;
use NotificationChannels\WebPush\PushSubscription;

class User extends Authenticatable
{
    public function twoFactorCodes()
    {
        return $this->hasMany(\App\Models\TwoFactorCode::class);
    }

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
        'is_verified',
    ];
    protected $casts = [
        'is_verified' => 'boolean',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Roles relationship
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Check if user has a role (or one of roles array).
     *
     * @param string|array $roles
     * @return bool
     */
    public function hasRole($roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Get the push subscriptions for web push notifications.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function routeNotificationForWebPush()
    {
        return PushSubscription::where('subscribable_type', self::class)
            ->where('subscribable_id', $this->id)
            ->get();
    }
}