<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $fillable = [
        'category_id', 'title', 'description', 'banner_image',
        'quizContents', 'spot_limit', 'entry_fees', 'prize_money', 'node_id',
        'start_time', 'end_time', 'quiz_timer', 'totalQuestion', 'quiz_over_at'
    ];

    protected $casts = [
        'quizContents' => 'collection',
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

    public function user_response(){
        return $this->hasOne(UserResponse::class);
    }
}
