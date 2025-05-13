<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $fillable = [
        'category_id', 'title', 'description', 'banner_image',
        'quizContents', 'spot_limit', 'entry_fees', 'prize_money', 'node_id',
        'start_time', 'end_time', 'quiz_timer', 'winners', 'totalQuestion', 'quiz_over_at', 'is_prize_distributed'
    ];

    protected $casts = [
        'category_id' => 'integer',
        'node_id' => 'integer',
        'quizContents' => 'collection',
        'spot_limit' => 'integer',
        'entry_fees' => 'integer',
        'prize_money' => 'integer',
        'start_time' => 'integer',  // could be 'datetime' if you're using timestamps
        'end_time' => 'integer',    // same as above
        'quiz_timer' => 'integer',
        'winners' => 'integer',
        'totalQuestion' => 'integer',
        'quiz_over_at' => 'integer',
        'is_prize_distributed' => 'boolean',
    ];
    

    public function category(){
        return $this->belongsTo(Category::class);
    }

    public function quiz_variants(){
        return $this->hasMany(QuizVariant::class);
    }

    public function quiz_sheet(){
        return $this->hasOne(QuizSheet::class);
    }

    public function user_responses(){
        return $this->hasMany(UserResponse::class);
    }
}
