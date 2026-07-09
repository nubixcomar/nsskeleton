<?php

declare(strict_types=1);

use App\Services\Http;

/**
 * v1.18 #1 — Cliente HTTP del core con transporte inyectable + retries (hallazgo 1.10).
 */

group('Http (transporte inyectable + retries)');

/** Ejecuta $fn con un transporte mock y lo resetea al final. */
$withTransport = static function (callable $transport, callable $fn): void {
    Http::setTransport($transport);
    try {
        $fn();
    } finally {
        Http::resetTransport();
    }
};

it('get() decodifica JSON del transporte', function () use ($withTransport) {
    $withTransport(
        static fn ($m, $u, $b, $h, $t) => ['status' => 200, 'body' => '{"hello":"world"}', 'error' => ''],
        function () {
            $r = Http::get('https://x.test/api');
            assertTrue($r['ok']);
            assertEquals(200, $r['status']);
            assertEquals('world', $r['data']['hello']);
        }
    );
});

it('request() con json setea body y Content-Type', function () use ($withTransport) {
    $captured = [];
    $withTransport(
        static function ($m, $u, $b, $h, $t) use (&$captured) {
            $captured = ['method' => $m, 'body' => $b, 'headers' => $h];
            return ['status' => 201, 'body' => '{"id":1}', 'error' => ''];
        },
        function () use (&$captured) {
            $r = Http::request('POST', 'https://x.test', ['json' => ['a' => 1]]);
            assertEquals(201, $r['status']);
            assertEquals(1, $r['data']['id']);
            assertEquals('POST', $captured['method']);
            assertContains('"a":1', (string) $captured['body']);
            assertTrue(in_array('Content-Type: application/json', $captured['headers'], true));
        }
    );
});

it('reintenta ante 5xx y termina OK', function () use ($withTransport) {
    $calls = 0;
    $withTransport(
        static function ($m, $u, $b, $h, $t) use (&$calls) {
            $calls++;
            return $calls < 3
                ? ['status' => 503, 'body' => '', 'error' => '']
                : ['status' => 200, 'body' => '{"ok":true}', 'error' => ''];
        },
        function () use (&$calls) {
            $r = Http::request('GET', 'https://x.test', ['retries' => 3, 'backoff_ms' => 0]);
            assertTrue($r['ok']);
            assertEquals(3, $r['attempts']);
            assertEquals(3, $calls);
        }
    );
});

it('agota los reintentos y devuelve el último error', function () use ($withTransport) {
    $withTransport(
        static fn ($m, $u, $b, $h, $t) => ['status' => 500, 'body' => '', 'error' => ''],
        function () {
            $r = Http::request('GET', 'https://x.test', ['retries' => 2, 'backoff_ms' => 0]);
            assertFalse($r['ok']);
            assertEquals(3, $r['attempts']); // 1 + 2 reintentos
        }
    );
});

it('NO reintenta ante 4xx', function () use ($withTransport) {
    $calls = 0;
    $withTransport(
        static function ($m, $u, $b, $h, $t) use (&$calls) {
            $calls++;
            return ['status' => 404, 'body' => '', 'error' => ''];
        },
        function () use (&$calls) {
            $r = Http::request('GET', 'https://x.test', ['retries' => 3, 'backoff_ms' => 0]);
            assertFalse($r['ok']);
            assertEquals(1, $calls, '404 no se reintenta');
        }
    );
});

it('reintenta ante fallo de conexión (status 0)', function () use ($withTransport) {
    $calls = 0;
    $withTransport(
        static function ($m, $u, $b, $h, $t) use (&$calls) {
            $calls++;
            return ['status' => 0, 'body' => '', 'error' => 'timeout'];
        },
        function () use (&$calls) {
            $r = Http::request('GET', 'https://x.test', ['retries' => 1, 'backoff_ms' => 0]);
            assertFalse($r['ok']);
            assertEquals(2, $calls);
            assertContains('timeout', $r['error']);
        }
    );
});

it('postJson sigue funcionando a través del transporte', function () use ($withTransport) {
    $withTransport(
        static fn ($m, $u, $b, $h, $t) => ['status' => 200, 'body' => '{"paid":true}', 'error' => ''],
        function () {
            $r = Http::postJson('https://x.test/pay', ['amount' => 100]);
            assertTrue($r['ok']);
            assertTrue($r['data']['paid']);
        }
    );
});
