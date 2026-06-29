<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Helper para construir configuraciones de gráficos (Chart.js) desde PHP.
 * Devuelve arrays que el partial `partials/chart` serializa a JSON.
 */
final class Charts
{
    /** Paleta base (se cicla si hace falta). */
    private const PALETTE = [
        '#6366f1', '#10b981', '#f59e0b', '#0ea5e9',
        '#f43f5e', '#8b5cf6', '#14b8a6', '#fb923c',
    ];

    /** @return array<int,string> */
    public static function palette(int $n): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = self::PALETTE[$i % count(self::PALETTE)];
        }
        return $out;
    }

    /**
     * @param array<int,string|int> $labels
     * @param array<int,int|float> $data
     * @return array<string,mixed>
     */
    public static function bar(string $id, string $title, array $labels, array $data): array
    {
        return [
            'id'     => $id,
            'type'   => 'bar',
            'title'  => $title,
            'labels' => $labels,
            'datasets' => [[
                'label'           => $title,
                'data'            => array_values($data),
                'backgroundColor' => self::palette(count($data)),
                'borderRadius'    => 6,
            ]],
            'options' => [
                'responsive' => true,
                'plugins'    => ['legend' => ['display' => false]],
                'scales'     => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            ],
        ];
    }

    /**
     * @param array<int,string|int> $labels
     * @param array<int,int|float> $data
     * @return array<string,mixed>
     */
    public static function doughnut(string $id, string $title, array $labels, array $data): array
    {
        return [
            'id'     => $id,
            'type'   => 'doughnut',
            'title'  => $title,
            'labels' => $labels,
            'datasets' => [[
                'data'            => array_values($data),
                'backgroundColor' => self::palette(count($data)),
            ]],
            'options' => [
                'responsive' => true,
                'plugins'    => ['legend' => ['position' => 'bottom']],
            ],
        ];
    }

    /**
     * @param array<int,string|int> $labels
     * @param array<int,int|float> $data
     * @return array<string,mixed>
     */
    public static function line(string $id, string $title, array $labels, array $data): array
    {
        return [
            'id'     => $id,
            'type'   => 'line',
            'title'  => $title,
            'labels' => $labels,
            'datasets' => [[
                'label'           => $title,
                'data'            => array_values($data),
                'borderColor'     => self::PALETTE[0],
                'backgroundColor' => 'rgba(99,102,241,0.15)',
                'fill'            => true,
                'tension'         => 0.3,
            ]],
            'options' => [
                'responsive' => true,
                'plugins'    => ['legend' => ['display' => false]],
                'scales'     => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            ],
        ];
    }
}
