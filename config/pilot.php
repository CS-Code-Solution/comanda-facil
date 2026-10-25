<?php

return [
    'backup_directory' => storage_path('app/private/operations-backups'),
    'restore_directory' => storage_path('app/private/restore-verification'),
    'backup_key' => env('OPERATIONS_BACKUP_KEY'),
    'postgres_bin' => env('OPERATIONS_POSTGRES_BIN', ''),
    'scheduled_backup' => (bool) env('OPERATIONS_SCHEDULED_BACKUP', false),
    'backup_time' => env('OPERATIONS_BACKUP_TIME', '03:00'),
    'timezone' => env('CATALOG_TIMEZONE', 'America/Fortaleza'),
    'backup_max_age_hours' => 26,
    'outbox_max_age_seconds' => 60,
    'publisher_max_age_seconds' => 30,
    'restore_max_bytes' => 10737418240,
    // No purge is executed until a separate retention decision and implementation.
    'purge_enabled' => false,
];
