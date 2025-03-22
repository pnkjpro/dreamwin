<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserResponse extends Model
{
    protected $fillable = [
        'user_id', 'quiz_id', 'quiz_variant_id', 'score', 'responseContents', 'status'
    ];

    protected $casts = [
        'responseContents' => 'collection'
    ];

    /** responseContents
     * question_id
     * answer_id
     * is_correct
     */
}
