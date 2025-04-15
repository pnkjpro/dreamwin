<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leaderboard extends Model
{
    protected $fillable = [
        'quiz_id', 'name', 'user_id', 'quiz_variant_id', 'user_response_id', 'score', 'reward', 'rank', 'duration', 'is_user'
    ];
}
