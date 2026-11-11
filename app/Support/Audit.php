<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Audit
{
    public static function log(string $action, ?string $entity = null, ?int $entityId = null, array $detail = [], ?int $userId = null): void
    {
        $req = request();
        AuditLog::query()->create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'detail' => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip' => $req?->ip(),
            'user_agent' => $req ? Str::limit((string) $req->userAgent(), 250, '') : null,
        ]);
    }
}
