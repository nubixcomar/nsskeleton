<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Throwable;

/**
 * Rate-limit por clave con ventana fija. Si la base falla, no bloquea (fail-open).
 */
final class RateLimiter
{
    /**
     * Registra un golpe y devuelve el estado del límite.
     * @return array{allowed:bool,remaining:int,limit:int}
     */
    public static function hit(string $key, int $limit, int $window = 60): array
    {
        $now = time();
        $windowStart = $now - ($now % max(1, $window));

        try {
            $row = Database::selectOne('SELECT window_start, count FROM rate_limits WHERE rkey = ? LIMIT 1', [$key]);
            if ($row === null) {
                Database::run(
                    'INSERT INTO rate_limits (rkey, window_start, count) VALUES (?, ?, 1)
                     ON DUPLICATE KEY UPDATE window_start = VALUES(window_start), count = 1',
                    [$key, $windowStart]
                );
                $count = 1;
            } elseif ((int) $row['window_start'] < $windowStart) {
                Database::run('UPDATE rate_limits SET window_start = ?, count = 1 WHERE rkey = ?', [$windowStart, $key]);
                $count = 1;
            } else {
                Database::run('UPDATE rate_limits SET count = count + 1 WHERE rkey = ?', [$key]);
                $count = (int) $row['count'] + 1;
            }
        } catch (Throwable) {
            return ['allowed' => true, 'remaining' => $limit, 'limit' => $limit];
        }

        return [
            'allowed'   => $count <= $limit,
            'remaining' => max(0, $limit - $count),
            'limit'     => $limit,
        ];
    }
}
