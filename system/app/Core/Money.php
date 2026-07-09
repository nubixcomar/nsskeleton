<?php

declare(strict_types=1);

namespace Core;

use InvalidArgumentException;

/**
 * Valor monetario inmutable, guardado en **unidades mínimas** (centavos) como entero para
 * evitar errores de punto flotante. Extracción genérica de nsCheckout (v1.x extracciones).
 *
 * Decimales y símbolo por divisa salen de un mapa built-in, overrideable en `config/money.php`.
 *
 *   Money::of('1234.50', 'ARS')->format();   // "$ 1.234,50"
 *   Money::ofMinor(100, 'USD')->add(Money::ofMinor(50, 'USD'));  // 1.50 USD
 */
final class Money
{
    /** Divisas built-in: código => [decimales, símbolo, separador_decimal, separador_miles]. */
    private const CURRENCIES = [
        'ARS' => [2, '$',   ',', '.'],
        'USD' => [2, 'US$', '.', ','],
        'EUR' => [2, '€',   ',', '.'],
        'BRL' => [2, 'R$',  ',', '.'],
        'CLP' => [0, '$',   ',', '.'],
        'MXN' => [2, '$',   '.', ','],
        'UYU' => [2, '$U',  ',', '.'],
        'JPY' => [0, '¥',   '.', ','],
        'GBP' => [2, '£',   '.', ','],
    ];

    private function __construct(
        public readonly int $minor,
        public readonly string $currency,
    ) {
    }

    /** Crea desde unidades MAYORES (ej. 1234.50 pesos). */
    public static function of(int|float|string $amount, string $currency): self
    {
        $currency = strtoupper($currency);
        $decimals = self::decimals($currency);
        $factor = 10 ** $decimals;
        // Redondeo a la unidad mínima (evita 0.1+0.2 en float).
        $minor = (int) round(((float) $amount) * $factor);
        return new self($minor, $currency);
    }

    /** Crea desde unidades MÍNIMAS (centavos). */
    public static function ofMinor(int $minor, string $currency): self
    {
        return new self($minor, strtoupper($currency));
    }

    public static function zero(string $currency): self
    {
        return new self(0, strtoupper($currency));
    }

    /** Decimales de la divisa (config/money.php override o built-in; default 2). */
    public static function decimals(string $currency): int
    {
        $meta = self::meta(strtoupper($currency));
        return (int) ($meta[0] ?? 2);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minor - $other->minor, $this->currency);
    }

    /** Multiplica por un factor (ej. cantidad o impuesto); redondea a la unidad mínima. */
    public function multiply(float $factor): self
    {
        return new self((int) round($this->minor * $factor), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    /** Valor en unidades mayores (float). Para mostrar/serializar; NO para acumular. */
    public function toMajor(): float
    {
        return $this->minor / (10 ** self::decimals($this->currency));
    }

    /**
     * Formatea con separadores y símbolo de la divisa.
     * @param array{symbol?:bool} $opts
     */
    public function format(array $opts = []): string
    {
        $withSymbol = $opts['symbol'] ?? true;
        [$decimals, $symbol, $decSep, $thoSep] = self::meta($this->currency)
            + [2, $this->currency, '.', ','];

        $number = number_format($this->toMajor(), (int) $decimals, (string) $decSep, (string) $thoSep);
        return $withSymbol ? trim($symbol . ' ' . $number) : $number;
    }

    public function __toString(): string
    {
        return $this->format() . ' ' . $this->currency;
    }

    /** @return array{0:int,1:string,2:string,3:string}|array<int,mixed> */
    private static function meta(string $currency): array
    {
        // Override por config/money.php (si existe): ['ARS' => [2,'$',',','.'], ...].
        $over = Config::get('money', 'currencies', []);
        if (is_array($over) && isset($over[$currency]) && is_array($over[$currency])) {
            return $over[$currency];
        }
        if (isset(self::CURRENCIES[$currency])) {
            return self::CURRENCIES[$currency];
        }
        // Divisa desconocida: 2 decimales, símbolo = código.
        return [2, $currency, '.', ','];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "No se pueden operar montos de distinta divisa: {$this->currency} vs {$other->currency}"
            );
        }
    }
}
