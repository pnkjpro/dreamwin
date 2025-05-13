<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'type', 'amount', 'description', 'reference_id', 'reference_type'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'integer',
        'reference_id' => 'integer',
    ]; 
}
