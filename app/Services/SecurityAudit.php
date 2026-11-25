<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SecurityAudit
{
    public static function record(string $action, string $type, ?int $id, array $metadata = [], ?int $actorId = null): void
    {
        DB::table('security_audits')->insert([
            'actor_id' => $actorId ?? AccountAccess::user()?->id, 'action' => $action,
            'subject_type' => $type, 'subject_id' => $id, 'metadata' => json_encode($metadata),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
