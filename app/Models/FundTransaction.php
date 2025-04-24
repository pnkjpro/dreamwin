<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'amount',
        'transaction_id',
        'description',
        'reference_id',
        'reference_type',
        'approved_status'
    ];
}
