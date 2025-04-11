<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HowVideos extends Model
{
    protected $fillable = [
        'title', 'description', 'youtube_id'
    ];
}
