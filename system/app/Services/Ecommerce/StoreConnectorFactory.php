<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Services\Settings;
use Throwable;

/**
 * Fábrica de conectores de tienda.
 *
 * Resuelve el driver de una plataforma y lo instancia con credenciales. Las
 * credenciales pueden venir explícitas (útil para multi-tienda) o leerse de la
 * configuración persistente (grupo `ecommerce` en `settings`, secretos cifrados),
 * igual que hace `AiConnector` con el grupo `ai`.
 *
 * Registro de plataformas: `system/config/ecommerce.php`.
 */
final class StoreConnectorFactory
{
    /** Driver por defecto si la config no especifica clase. */
    private const DRIVERS = [
        'nubixstore'  => NubixstoreConnector::class,
        'shopify'     => ShopifyConnector::class,
        'tiendanube'  => TiendaNubeConnector::class,
        'woocommerce' => WooCommerceConnector::class,
        'magento'     => MagentoConnector::class,
    ];

    /** @return array<string,mixed> Registro completo de plataformas (config/ecommerce.php). */
    public static function registry(): array
    {
        $file = self::configFile();
        if (is_file($file)) {
            /** @var array<string,mixed> $cfg */
            $cfg = require $file;
            if (isset($cfg['platforms']) && is_array($cfg['platforms'])) {
                return $cfg['platforms'];
            }
        }
        // Fallback: registro mínimo derivado de los drivers conocidos.
        $out = [];
        foreach (self::DRIVERS as $key => $class) {
            $out[$key] = ['label' => ucfirst($key), 'driver' => $class];
        }
        return $out;
    }

    /** @return array<int,string> Claves de plataforma soportadas. */
    public static function platforms(): array
    {
        return array_keys(self::registry());
    }

    /**
     * Crea un conector para una plataforma con credenciales explícitas.
     * @param array<string,mixed> $credentials
     * @throws \InvalidArgumentException si la plataforma no está registrada.
     */
    public static function make(string $platform, array $credentials, int $timeout = 20): StoreConnector
    {
        $platform = strtolower(trim($platform));
        $registry = self::registry();
        $driver   = self::DRIVERS[$platform]
            ?? (is_string($registry[$platform]['driver'] ?? null) ? $registry[$platform]['driver'] : null);

        if ($driver === null || !class_exists($driver) || !is_subclass_of($driver, StoreConnector::class)) {
            throw new \InvalidArgumentException("Plataforma de ecommerce no soportada: '{$platform}'.");
        }
        /** @var StoreConnector $instance */
        $instance = new $driver($credentials, $timeout);
        return $instance;
    }

    /**
     * Crea el conector de la tienda activa según la configuración persistente.
     *
     * Lee el grupo `ecommerce` de `settings`:
     *   ecommerce.platform        → plataforma activa (default: config 'default')
     *   ecommerce.<campo>         → credenciales de esa plataforma (cifradas los secretos)
     *
     * Devuelve null si no hay plataforma/credenciales configuradas (degradación
     * controlada, no lanza).
     */
    public static function fromSettings(?string $platform = null): ?StoreConnector
    {
        try {
            $g = Settings::group('ecommerce');
        } catch (Throwable) {
            $g = [];
        }

        $platform = $platform ?? (string) ($g['platform'] ?? self::defaultPlatform());
        if ($platform === '') {
            return null;
        }

        // Credenciales = todo el grupo salvo la clave 'platform'.
        $creds = $g;
        unset($creds['platform']);
        if ($creds === []) {
            return null;
        }

        try {
            return self::make($platform, $creds);
        } catch (Throwable) {
            return null;
        }
    }

    public static function defaultPlatform(): string
    {
        $file = self::configFile();
        if (is_file($file)) {
            /** @var array<string,mixed> $cfg */
            $cfg = require $file;
            if (is_string($cfg['default'] ?? null)) {
                return $cfg['default'];
            }
        }
        return 'nubixstore';
    }

    private static function configFile(): string
    {
        // system/app/Services/Ecommerce/ → system/config/ecommerce.php
        return \dirname(__DIR__, 3) . '/config/ecommerce.php';
    }
}
