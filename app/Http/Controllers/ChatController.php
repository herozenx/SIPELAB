<?php

namespace App\Http\Controllers;

use App\Models\ChatThread;
use App\Services\Chat;
use Illuminate\Http\Request;

/**
 * Chat langsung via web (tanpa layanan pihak ketiga): browser menarik pesan baru tiap beberapa detik.
 * Pengguna jasa punya satu percakapan; semua admin & operator lab bisa membalas.
 */
class ChatController extends Controller
{
    public function __construct(private Chat $chat) {}

    public function index(Request $request)
    {
        $u = $request->user();

        if (! $u->isPetugasBalai()) {
            $thread = ChatThread::query()->firstOrCreate(['user_id' => $u->id]);

            return view('chat.index', [
                'u' => $u, 'thread' => $thread->load('pengguna'), 'threads' => collect(), 'belumPer' => collect(),
                'online' => $this->chat->online(),
            ]);
        }

        $cari = trim((string) $request->query('cari'));
        $q = ChatThread::query()->whereNotNull('last_message_at')->with(['pengguna', 'pesanTerakhir'])->orderByDesc('last_message_at');
        if ($cari !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr($cari, 0, 60)).'%';
            $q->whereHas('pengguna', fn ($w) => $w->where('nama', 'like', $like)->orWhere('instansi', 'like', $like));
        }
        $threads = $q->limit(100)->get();
        $thread = $request->query('t')
            ? ChatThread::query()->with('pengguna')->find((int) $request->query('t'))
            : $threads->first();

        return view('chat.index', [
            'u' => $u, 'thread' => $thread, 'threads' => $threads, 'cari' => $cari,
            'belumPer' => $this->chat->belumPerThread(), 'online' => $this->chat->online(),
        ]);
    }

    /** Pesan baru setelah id tertentu (polling), sekaligus menandai sudah dibaca. */
    public function pesan(Request $request, ChatThread $thread)
    {
        $u = $request->user();
        abort_unless($this->chat->bolehAkses($u, $thread), 404);

        $setelah = max(0, (int) $request->query('setelah'));
        $q = $thread->pesan()->with('pengirim.lab');
        $rows = $setelah > 0
            ? $q->where('id', '>', $setelah)->orderBy('id')->limit(200)->get()
            : $q->orderByDesc('id')->limit(100)->get()->reverse()->values();

        if ($rows->isNotEmpty()) {
            $this->chat->tandaiDibaca($u, $thread, (int) $rows->max('id'));
        }

        return response()->json([
            'pesan' => $rows->map(fn ($m) => $this->chat->format($m, $u))->values(),
            'online' => $this->chat->online(),
            'pengguna_online' => $u->isPetugasBalai() ? (bool) $thread->pengguna?->online() : null,
        ])->header('Cache-Control', 'no-store');
    }

    public function kirim(Request $request, ChatThread $thread)
    {
        $u = $request->user();
        abort_unless($this->chat->bolehAkses($u, $thread), 404);
        $request->merge(['isi' => trim((string) $request->input('isi'))]);
        $data = $request->validate(['isi' => ['required', 'string', 'max:2000']], [
            'isi.required' => 'Pesan tidak boleh kosong.',
            'isi.max' => 'Pesan maksimal 2000 karakter.',
        ]);

        $m = $this->chat->kirim($u, $thread, $data['isi']);

        return response()->json(['pesan' => $this->chat->format($m->load('pengirim.lab'), $u)], 201);
    }

    /** Lencana menu & daftar online (dipanggil berkala dari semua halaman). */
    public function status(Request $request)
    {
        return response()->json([
            'belum' => $this->chat->belum($request->user()),
            'online' => count($this->chat->online()),
        ])->header('Cache-Control', 'no-store');
    }
}
