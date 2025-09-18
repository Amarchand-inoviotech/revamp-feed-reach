<?php

namespace App\Models\Traits;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

trait ModelTrait
{
    protected $slugNum = 0;

    public function toFill(array $payload, array $exceptColumns = [])
    {
        $acceptColumns = array_diff($this->getColumns(), $exceptColumns);
        $this->fill(
            collect($payload)
                ->only($acceptColumns)
                ->all()
        );
        return $this;
    }

    public function getColumns()
    {
        // check model property fillable
        if (property_exists($this, 'fillable')) {
            return $this->getFillable();
        } elseif (property_exists($this, 'guarded')) {
            $guardedColumns = $this->getGuarded();

            if (in_array('*', $guardedColumns)) {
                return Schema::getColumnListing($this->getTable());
            }
            return array_diff(Schema::getColumnListing($this->getTable()), $guardedColumns);
        } else {
            return Schema::getColumnListing($this->getTable());
        }
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function scopeSort($query)
    {
        $sortBy = request()->get('sort_by', 'id'); // default to 'id'
        $sortOrder = request()->get('sort_direction', 'desc'); // default to 'desc'

        $query->orderBy($sortBy, $sortOrder);
        return $query;
    }

    protected static function boot()
    {
        parent::boot();
        if (Schema::hasColumn((new self)->getTable(), 'uuid')) {
            static::creating(function ($model) {
                $model->uuid = Str::uuid();
            });
        }

        if (Schema::hasColumn((new self())->getTable(), 'author_id')) {
            static::creating(function ($model) {
                $auth = Auth::user();
                if(!$auth){
                    $auth = User::find(1);
                }
                if ($auth) {
                    $model->author_id = $auth->id;
                    $model->author_type = $auth->getMorphClass();
                }
            });
        }
    }

    public function attachments()
    {
        return $this->morphToMany(Attachment::class, 'model');
    }

    public function author()
    {
        return $this->morphTo('author');
    }
}
