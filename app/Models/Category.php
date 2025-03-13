<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'icon_color',
        'banner_image',
        'is_active',
        'display_order',
    ];
}
