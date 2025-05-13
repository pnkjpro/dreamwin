<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leaderboard extends Model
{
    protected $fillable = [
        'quiz_id', 'name', 'user_id', 'quiz_variant_id', 'user_response_id', 'score', 'reward', 'rank', 'duration'
    ];

    protected $casts = [
        'quiz_id' => 'integer',
        'user_id' => 'integer',
        'quiz_variant_id' => 'integer',
        'user_response_id' => 'integer',
        'score' => 'integer',
        'reward' => 'integer',
        'rank' => 'integer',
        'duration' => 'integer',
    ];
    
}
