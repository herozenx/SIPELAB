<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
