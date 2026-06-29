<?php

declare(strict_types=1);

use App\Services\CronExpression as C;

group('CronExpression');

it('valida "*/5 * * * *"', fn () => assertTrue(C::isValid('*/5 * * * *')));
it('rechaza expresión de 4 campos', fn () => assertFalse(C::isValid('* * * *')));
it('rechaza basura', fn () => assertFalse(C::isValid('a b c d e')));

it('"0 3 * * *" está due a las 03:00', fn () => assertTrue(C::isDue('0 3 * * *', new DateTimeImmutable('2026-06-22 03:00:00'))));
it('"0 3 * * *" no está due a las 03:01', fn () => assertFalse(C::isDue('0 3 * * *', new DateTimeImmutable('2026-06-22 03:01:00'))));
it('"*/5" due en el minuto 15', fn () => assertTrue(C::isDue('*/5 * * * *', new DateTimeImmutable('2026-06-22 10:15:00'))));
it('"*/5" no due en el minuto 16', fn () => assertFalse(C::isDue('*/5 * * * *', new DateTimeImmutable('2026-06-22 10:16:00'))));
it('día del mes 22 due', fn () => assertTrue(C::isDue('0 0 22 * *', new DateTimeImmutable('2026-06-22 00:00:00'))));
it('lista "1,15" due el día 15', fn () => assertTrue(C::isDue('30 9 1,15 * *', new DateTimeImmutable('2026-06-15 09:30:00'))));

it('nextRunAfter "0 0 1 * *" → 2026-07-01 00:00', function () {
    $n = C::nextRunAfter('0 0 1 * *', new DateTimeImmutable('2026-06-22 12:00:00'));
    assertNotNull($n);
    assertEquals('2026-07-01 00:00', $n->format('Y-m-d H:i'));
});
it('nextRunAfter "*/15" desde 10:07 → 10:15', function () {
    $n = C::nextRunAfter('*/15 * * * *', new DateTimeImmutable('2026-06-22 10:07:00'));
    assertNotNull($n);
    assertEquals('10:15', $n->format('H:i'));
});
