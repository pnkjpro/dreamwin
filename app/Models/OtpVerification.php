<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    protected $fillable = [
        'mobile', 'email', 'otp', 'valid_on', 'validated_at', 'is_verified', 'is_registered'
    ];
}
