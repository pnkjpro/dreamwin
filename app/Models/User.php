<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'avatar', 'email', 'email_verified_at', 'password', 'mobile', 'mobile_verified_at', 'funds', 'upi_id', 'refer_code', 'refer_by', 'is_reward_given'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'mobile_verified_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function lifelines() {
        return $this->hasMany(UserLifeline::class);
    }

    public function user_responses(){
        return $this->hasMany(UserResponse::class);
    }
    
    public function lifelineUsages() {
        return $this->hasMany(LifelineUsage::class);
    }

    public function fund_transactions(){
        return $this->hasMany(FundTransaction::class);
    }
    
    // Helper method to check if user has lifeline
    public function hasLifeline($lifelineId) {
        return $this->lifelines()
            ->where('lifeline_id', $lifelineId)
            ->where('quantity', '>', 0)
            ->exists();
    }

    public function isAdmin(){
        return $this->is_admin === 1;
    }

    public function referredBy(){
        return $this->belongsTo(User::class, 'refer_by');
    }

    public function leaderboard(){
        return $this->hasOne(leaderboard::class);
    }
}
