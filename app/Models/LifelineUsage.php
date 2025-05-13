<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LifelineUsage extends Model
{
    protected $fillable = [
        'user_id', 'lifeline_id', 'user_response_id', 'question_id', 'used_at', 'result_data'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'lifeline_id' => 'integer',
        'user_response_id' => 'integer',
        'question_id' => 'integer',
        'used_at' => 'datetime',
        'result_data' => 'array',
    ];  

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function user_response(){
        return $this->belongsTo(UserResponse::class);
    }
}
