<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    protected $fillable = [
        'mobile', 'email', 'otp', 'valid_on', 'validated_at', 'is_verified', 'is_registered'
    ];

    protected $casts = [
        'otp' => 'integer',
        'valid_on' => 'integer', // if it's a timestamp or ID that should be an integer
        'validated_at' => 'integer', // assuming it's a timestamp
        'is_verified' => 'boolean',
        'is_registered' => 'boolean',
    ];
    
}
