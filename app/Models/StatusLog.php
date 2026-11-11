<?php

namespace App\Models;

use App\Enums\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'status_log';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => Status::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
