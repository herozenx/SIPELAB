<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Satu percakapan per pengguna jasa; bisa dibalas oleh semua admin & operator. */
class ChatThread extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'staff_read_id' => 'integer', 'user_read_id' => 'integer'];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pesan(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'thread_id');
    }

    public function pesanTerakhir(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'thread_id')->latestOfMany();
    }
}
