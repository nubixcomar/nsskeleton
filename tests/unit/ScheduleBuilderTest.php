<?php

declare(strict_types=1);

use App\Services\ScheduleBuilder;

group('ScheduleBuilder (I2)');

it('fromPreset minutes', function () {
    assertEquals('*/10 * * * *', ScheduleBuilder::fromPreset(['type' => 'minutes', 'every' => 10]));
});

it('fromPreset hourly', function () {
    assertEquals('15 * * * *', ScheduleBuilder::fromPreset(['type' => 'hourly', 'minute' => 15]));
});

it('fromPreset daily', function () {
    assertEquals('30 3 * * *', ScheduleBuilder::fromPreset(['type' => 'daily', 'hour' => 3, 'minute' => 30]));
});

it('fromPreset weekly (lunes 9:00)', function () {
    assertEquals('0 9 * * 1', ScheduleBuilder::fromPreset(['type' => 'weekly', 'hour' => 9, 'minute' => 0, 'dow' => 1]));
});

it('fromPreset monthly (día 1, 00:00)', function () {
    assertEquals('0 0 1 * *', ScheduleBuilder::fromPreset(['type' => 'monthly', 'hour' => 0, 'minute' => 0, 'dom' => 1]));
});

it('fromPreset clampa valores fuera de rango', function () {
    assertEquals('*/59 * * * *', ScheduleBuilder::fromPreset(['type' => 'minutes', 'every' => 999]));
    assertEquals('59 23 * * *', ScheduleBuilder::fromPreset(['type' => 'daily', 'hour' => 99, 'minute' => 99]));
});

group('ScheduleBuilder::describe (I2)');

it('describe reconoce patrones comunes', function () {
    assertEquals('Cada 5 minutos', ScheduleBuilder::describe('*/5 * * * *'));
    assertEquals('Cada minuto', ScheduleBuilder::describe('* * * * *'));
    assertEquals('Cada hora al minuto 15', ScheduleBuilder::describe('15 * * * *'));
    assertEquals('Todos los días a las 03:30', ScheduleBuilder::describe('30 3 * * *'));
    assertEquals('Los Lunes a las 09:00', ScheduleBuilder::describe('0 9 * * 1'));
    assertEquals('El día 1 de cada mes a las 00:00', ScheduleBuilder::describe('0 0 1 * *'));
});

it('describe deja la expresión cruda si no la reconoce', function () {
    assertEquals('5,30 1-4 * * *', ScheduleBuilder::describe('5,30 1-4 * * *'));
    assertEquals('mala', ScheduleBuilder::describe('mala'));
});

it('roundtrip fromPreset → describe', function () {
    $expr = ScheduleBuilder::fromPreset(['type' => 'weekly', 'hour' => 18, 'minute' => 45, 'dow' => 5]);
    assertEquals('Los Viernes a las 18:45', ScheduleBuilder::describe($expr));
});
