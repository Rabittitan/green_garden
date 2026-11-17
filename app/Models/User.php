<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// implements MustVerifyEmail: bật tính năng xác thực email của Laravel
// (thêm hasVerifiedEmail(), sendEmailVerificationNotification(), middleware 'verified' hoạt động)
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    // Giữ 'role' trong fillable để seeder hiện tại vẫn tạo được admin.
    // Khuyến nghị: bỏ 'role' khỏi đây và trong seeder dùng User::forceCreate([...]).
    protected $fillable = ['name', 'email', 'password', 'role', 'phone', 'address'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }

    public function orders()            { return $this->hasMany(Order::class); }
    public function reviews()           { return $this->hasMany(PlantReview::class); }
    public function comments()          { return $this->hasMany(Comment::class); }
    public function couponRedemptions() { return $this->hasMany(CouponRedemption::class); }
}