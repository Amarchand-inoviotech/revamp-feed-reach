<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use ModelTrait,SoftDeletes;

    protected $fillable = [
        'country_id ',
        'locale ',
        'name',
        'slug',
        'domain',
        'email',
        'phone',
        'logo_id',
        'address',
        'invoice_url'
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];


    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->slug) && !empty($model->name)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function country()
    {
        return $this->belongsTo(Country::class, );
    }

    public function logo()
    {
        return $this->belongsTo(Attachment::class, 'logo_id');
    }
}
