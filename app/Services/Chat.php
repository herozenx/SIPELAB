<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Chat langsung: satu percakapan per pengguna jasa, dibalas bersama oleh semua admin & operator.
 * "Sudah dibaca" dicatat per sisi (pengguna / pihak Balai) memakai id pesan terakhir yang dilihat.
 */
class Chat
{
    public function bolehAkses(User $u, ChatThread $t): bool
    {
        return $u->isPetugasBalai() || $t->user_id === $u->id;
    }

    /** Jumlah pesan belum dibaca untuk lencana menu. */
    public function belum(User $u): int
    {
        if (! $u->isPetugasBalai()) {
            $t = ChatThread::query()->where('user_id', $u->id)->first();

            return $t ? ChatMessage::query()->where('thread_id', $t->id)->where('sender_id', '!=', $u->id)->where('id', '>', $t->user_read_id)->count() : 0;
        }

        return (int) DB::table('chat_messages as m')
            ->join('chat_threads as t', 't.id', '=', 'm.thread_id')
            ->whereColumn('m.sender_id', 't.user_id')
            ->whereColumn('m.id', '>', 't.staff_read_id')
            ->count();
    }

    /** @return Collection<int, int> thread_id => jumlah pesan pengguna yang belum dibaca pihak Balai */
    public function belumPerThread(): Collection
    {
        return DB::table('chat_messages as m')
            ->join('chat_threads as t', 't.id', '=', 'm.thread_id')
            ->whereColumn('m.sender_id', 't.user_id')
            ->whereColumn('m.id', '>', 't.staff_read_id')
            ->selectRaw('m.thread_id, COUNT(*) as n')->groupBy('m.thread_id')->pluck('n', 'thread_id');
    }

    /** Admin & operator yang sedang online. @return array<int, array{nama:string, jabatan:string, inisial:string}> */
    public function online(): array
    {
        return User::query()->petugasBalai()->online()->with('lab')->orderBy('role')->orderBy('nama')->get()
            ->map(fn (User $x) => ['nama' => $x->nama, 'jabatan' => $x->jabatan(), 'inisial' => $x->initials()])->all();
    }

    public function tandaiDibaca(User $u, ChatThread $t, int $sampaiId): void
    {
        $kolom = $u->isPetugasBalai() ? 'staff_read_id' : 'user_read_id';
        if ($sampaiId > $t->{$kolom}) {
            ChatThread::query()->whereKey($t->id)->where($kolom, '<', $sampaiId)->update([$kolom => $sampaiId]);
            $t->{$kolom} = $sampaiId;
        }
    }

    public function kirim(User $u, ChatThread $t, string $isi): ChatMessage
    {
        return DB::transaction(function () use ($u, $t, $isi) {
            $m = ChatMessage::query()->create(['thread_id' => $t->id, 'sender_id' => $u->id, 'body' => $isi]);
            ChatThread::query()->whereKey($t->id)->update(['last_message_at' => now(), 'updated_at' => now()]);
            $this->tandaiDibaca($u, $t, $m->id);

            return $m;
        });
    }

    /** Bentuk pesan untuk dikirim ke browser (dirender dengan textContent, bukan HTML). */
    public function format(ChatMessage $m, User $penonton): array
    {
        $p = $m->pengirim;

        return [
            'id' => $m->id,
            'isi' => $m->body,
            'waktu' => tanggal($m->created_at),
            'nama' => $p?->nama ?? 'Akun dihapus',
            'jabatan' => $p?->isPetugasBalai() ? $p->jabatan() : 'Pengguna jasa',
            'saya' => $m->sender_id === $penonton->id,
            'balai' => (bool) $p?->isPetugasBalai(),
        ];
    }
}
