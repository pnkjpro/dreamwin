<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserResponse extends Model
{
    protected $fillable = [
        'user_id', 'quiz_id', 'node_id', 'quiz_variant_id', 'score', 'responseContents', 'status'
    ];

    protected $casts = [
        'responseContents' => 'collection'
    ];

    public function lifeline_usages(){
        return $this->hasMany(LifelineUsage::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function quiz(){
        return $this->belongsTo(Quiz::class);
    }

    /** responseContents
     * question_id
     * answer_id
     * is_correct
     */
}
