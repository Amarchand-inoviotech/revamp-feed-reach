<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, ModelTrait, HasFactory, HasRoles,SoftDeletes;

    /**
     * The guard name for permissions.
     *
     * @var string
     */
    protected $guard_name = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'address_id',
        'username',
        'name',
        'email',
        'password',
        'avatar_id',
        'gender',
        'dob',
        'phone',
        'two_factor',
        'notification'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function otpTokens()
    {
        return $this->morphMany(OtpToken::class, 'model');
    }

    public function avatar()
    {
        return $this->belongsTo(Attachment::class, 'avatar_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function businesses()  {
        return $this->morphMany(Business::class,'author');
    }

    public function addresses()  {
        return $this->morphMany(Address::class,'author');
    }

    public function cards()  {
        return $this->morphMany(Card::class,'author');
    }

    public function customers() {
        return $this->hasMany(Customer::class);
    }

}
