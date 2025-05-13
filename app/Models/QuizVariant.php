<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizVariant extends Model
{
    protected $fillable = [
        'quiz_id', 'entry_fee', 'prize', 'prize_contents', 'slot_limit', 'status'
    ];

    protected $casts = [
        'quiz_id' => 'integer',
        'entry_fee' => 'integer',
        'prize' => 'integer',
        'prize_contents' => 'array',
        'slot_limit' => 'integer',
    ];  

    public function quiz(){
        return $this->belongsTo(Quiz::class);
    }

    public function user_responses()
    {
        return $this->hasMany(UserResponse::class);
    }
}
