<?php

namespace App\Services;

use App\Models\ProductWidget;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AccountAccess
{
    public static function user(): ?User
    {
        return Auth::guard('internal')->user() ?? Auth::guard('web')->user();
    }

    public static function manages(ProductWidget $product): bool
    {
        $user = self::user();
        return $user && ($user->isSystemAdmin() || ($user->hasPermission('products.manage_own') && $user->id === $product->merchant_user_id));
    }
}
