<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLifeline extends Model
{
    protected $fillable = [
        'user_id', 'lifeline_id', 'quantity', 'last_used_at'
    ];
}
