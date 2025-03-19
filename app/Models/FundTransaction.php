<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'amount',
        'approved_status'
    ];
}
