<?php

namespace App\Application\Catalog;

use Illuminate\Support\Facades\DB;

class CatalogLock
{
    public const KEY = 2026102403;

    public static function exclusive(): void
    {
        DB::select('select pg_advisory_xact_lock(?)', [self::KEY]);
    }

    public static function shared(): void
    {
        DB::select('select pg_advisory_xact_lock_shared(?)', [self::KEY]);
    }

    public static function revision(): int
    {
        return (int) DB::table('catalog_state')->where('id', 1)->value('revision');
    }
}
