<?php

namespace App\Queries;

use Illuminate\Support\Facades\DB;

class AuditTable
{
    public static function audit($ip, $table, $what_did, $data): void
    {
        // User
        $user = auth()->user();
        $audit  = [
            'user_id'   => $user->id,
            'ip'        => $ip,
            'table'     => $table,
            'what_did'  => $what_did,
            'data'      => json_encode($data)
        ];
        DB::table('tb_audit')->insert($audit);
    }
}
