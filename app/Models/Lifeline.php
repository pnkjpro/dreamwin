<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lifeline extends Model
{
    protected $fillable = [
        'name', 'alias', 'description', 'cost', 'icon', 'icon_color',
        'is_active', 'cooldown_period', 'effect_description'
    ];

    public function lifeline_usage(){
        return $this->hasOne(LifelineUsage::class);
    }
}
