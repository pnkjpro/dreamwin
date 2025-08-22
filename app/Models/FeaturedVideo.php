<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeaturedVideo extends Model
{
    protected $fillable = [
        'title',
        'description',
        'youtubeUrl',
        'thumbnail',
        'duration',
        'views',
        'video_url',
        'is_active',
        'is_featured',
        'is_premium'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_premium' => 'boolean'
    ];
}
