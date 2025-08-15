<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'amount',
        'razorpay_order_id',
        'transaction_id',
        'description',
        'reference_id',
        'reference_type',
        'approved_status'
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function reference(){
        return $this->morphTo();
    }
}
