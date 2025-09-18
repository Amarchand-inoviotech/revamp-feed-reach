<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpToken extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'type',
        'token',
        'expires_at'
    ];

    public function model()
    {
        return $this->morphTo();
    }
}
