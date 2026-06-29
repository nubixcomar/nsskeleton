<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Core\Model;

final class AiLog extends Model
{
    protected static string $table = 'ai_log';

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 15): array
    {
        try {
            return Database::select('SELECT * FROM ai_log ORDER BY id DESC LIMIT ' . (int) $limit);
        } catch (\Throwable) {
            return [];
        }
    }
}
