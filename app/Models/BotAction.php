<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotAction extends Model
{
    protected $fillable = [
        'user_id', 'quiz_id', 'quiz_variant_id', 'question_attempts', 'rank', 'duration'
    ];
}
