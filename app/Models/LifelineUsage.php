<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LifelineUsage extends Model
{
    protected $fillable = [
        'user_id', 'lifeline_id', 'user_response_id', 'question_id', 'used_at', 'result_data'
    ];

    protected $casts = [
        'result_data' => 'array'
    ];
}
