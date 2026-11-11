<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_log';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
