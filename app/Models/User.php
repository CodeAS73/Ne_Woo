<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'is_active' => 'boolean',
            'session_version' => 'integer',
            'reporting_permissions' => 'array',
            'can_manage_fee_tiers' => 'boolean',
            'can_manage_buyer_fees' => 'boolean',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function kyc()
    {
        return $this->hasOne(MerchantKyc::class);
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $definition = config('rbac.roles.'.$this->role?->name);
        if (! $definition || $definition['type'] !== $this->account_type) {
            return false;
        }
        if ($permission === 'fees.manage' && ! $this->isSystemAdmin()) {
            return $this->account_type === 'internal' && $this->role?->name === 'supervisor' && $this->can_manage_fee_tiers;
        }
        if ($permission === 'buyer_fees.manage' && ! $this->isSystemAdmin()) {
            return $this->account_type === 'internal' && $this->role?->name === 'supervisor' && $this->can_manage_buyer_fees;
        }
        if ($this->role->name === 'supervisor' && array_key_exists($permission, config('rbac.supervisor_reporting'))) {
            return in_array($permission, $this->reporting_permissions ?? [], true);
        }

        return in_array('*', $definition['permissions'], true) || in_array($permission, $definition['permissions'], true);
    }

    public function isSystemAdmin(): bool
    {
        return $this->is_active && $this->account_type === 'internal' && $this->role?->name === 'system_admin';
    }

    public function canCreateWidgets(): bool
    {
        return $this->isSystemAdmin() || ($this->hasPermission('products.create') && $this->kyc?->status === 'approved');
    }
}
