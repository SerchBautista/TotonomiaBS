<?php

namespace App\Models;

use App\Notifications\PasswordResetNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasRoles;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected string $guard_name = 'api';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'stripe_customer_id',
        'subscription_ends_at',
        'default_workspace_id',
        'theme',
        'locale',
        'timezone',
        'two_factor_enabled',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
        ];
    }

    protected function getDefaultGuardName(): string
    {
        return 'api';
    }

    /** @return BelongsTo<Workspace, $this> */
    public function defaultWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'default_workspace_id');
    }

    /** @return HasMany<Workspace, $this> */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /** @return BelongsToMany<Workspace, $this> */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /** @return HasMany<OtherPaymentMethod, $this> */
    public function otherPaymentMethods(): HasMany
    {
        return $this->hasMany(OtherPaymentMethod::class);
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<PushDevice, $this> */
    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }

    /** @return HasMany<TwoFactorSession, $this> */
    public function twoFactorSessions(): HasMany
    {
        return $this->hasMany(TwoFactorSession::class);
    }

    /** @return HasMany<SubscriptionPayment, $this> */
    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->orderByDesc('paid_at');
    }

    /**
     * Route notifications for the FCM channel.
     *
     * @return list<string>
     */
    public function routeNotificationForFcm(): array
    {
        return $this->pushDevices()
            ->whereNull('revoked_at')
            ->where('notification_permission', 'granted')
            ->pluck('fcm_token')
            ->all();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_ends_at !== null
            && $this->subscription_ends_at->isFuture();
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new PasswordResetNotification($token));
    }
}
