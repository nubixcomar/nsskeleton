<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Core\Model;

final class EmailLog extends Model
{
    protected static string $table = 'email_log';

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 50): array
    {
        return Database::select(
            'SELECT * FROM email_log ORDER BY id DESC LIMIT ' . (int) $limit
        );
    }
}
