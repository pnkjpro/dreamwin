<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotAction extends Model
{
    protected $fillable = [
        'user_id', 'quiz_id', 'quiz_variant_id', 'question_attempts', 'rank', 'duration'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'quiz_id' => 'integer',
        'quiz_variant_id' => 'integer',
        'question_attempts' => 'integer', // assuming it's a count
        'rank' => 'integer',              // assuming it's a number
        'duration' => 'integer',          // assuming it's in seconds or minutes
    ];
    
}
