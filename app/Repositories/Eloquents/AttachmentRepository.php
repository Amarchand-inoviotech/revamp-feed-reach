<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Repositories\Contracts\AttachmentRepositoryContract;
use Illuminate\Database\Eloquent\Model;

class AttachmentRepository extends BaseRepository implements AttachmentRepositoryContract
{
     public function __construct(Attachment $model)
    {
        parent::__construct($model, AttachmentResource::class);
    }
}
