<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use ModelTrait,SoftDeletes,HasFactory;
    const IMAGE_RESIZES =
    [
        'icon' => 50,
        'mi' => 100,
        'xs' => 250,
        'sm' => 720,
    ];
    const UPLOAD_ORIGINAL = false;

    protected $fillable = [
        'md5',
        'title',
        'type',
        'comment',
        'path',
        'width',
        'height',
        'orientation',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

}
