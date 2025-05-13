<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeBanner extends Model
{
    protected $fillable = [
        'title', 'banner_path', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'integer',
    ];
}
