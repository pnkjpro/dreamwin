<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserResponse extends Model
{
    protected $fillable = [
        'user_id', 'quiz_id', 'score', 'responseContents'
    ];

    protected $casts = [
        'responseContents' => 'array'
    ];
}
