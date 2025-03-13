<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $fillable = [
        'category_id', 'title', 'description', 'banner_image',
        'quizContents', 'spot_limit', 'entry_fees', 'prize_money', 'node_id'
    ];

    protected $casts = [
        'quizContents' => 'array',
    ];
}
