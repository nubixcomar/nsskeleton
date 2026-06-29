<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Core\Model;

final class BackupLog extends Model
{
    protected static string $table = 'backup_log';

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 20): array
    {
        try {
            return Database::select('SELECT * FROM backup_log ORDER BY id DESC LIMIT ' . (int) $limit);
        } catch (\Throwable) {
            return [];
        }
    }
}
