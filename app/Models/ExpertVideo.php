<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpertVideo extends Model
{
    protected $fillable = [
        'title',
        'description',
        'videoUrl',
        'pdfUrl',
        'price',
        'thumbnail',
        'duration',
        'is_active',
        'is_featured',
        'is_premium',
        'is_deleted'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_premium' => 'boolean',
        'is_deleted' => 'boolean',
    ];
}
