<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantKyc extends Model
{
    protected $table = 'merchant_kyc';
    protected $guarded = ['id'];
    protected $hidden = ['details', 'document_path'];

    protected function casts(): array
    {
        return ['details' => 'encrypted:array', 'reviewed_at' => 'immutable_datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
