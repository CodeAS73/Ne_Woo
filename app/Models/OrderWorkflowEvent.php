<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderWorkflowEvent extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['note'];

    protected function casts(): array
    {
        return ['note' => 'encrypted'];
    }
}
