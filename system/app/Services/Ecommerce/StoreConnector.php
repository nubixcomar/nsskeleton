<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

/**
 * Contrato genérico de conexión con la API de una tienda de ecommerce.
 *
 * Define las operaciones comunes a cualquier plataforma (catálogo, pedidos,
 * clientes, health-check) más un acceso crudo (get/post) para endpoints
 * específicos de cada plataforma. Cada driver concreto
 * (Nubixstore/Shopify/TiendaNube/WooCommerce/Magento) traduce estas operaciones
 * a sus propias rutas y modelo de auth.
 *
 * Toda respuesta se normaliza a:
 *   ['ok'=>bool, 'status'=>int, 'data'=>mixed, 'error'=>?string]
 * donde `ok` es true si HTTP 2xx (y, si la plataforma lo indica, sin error de
 * negocio en el body). Nunca se lanza: los errores viajan en `error`.
 */
interface StoreConnector
{
    /** Clave de la plataforma (p. ej. 'nubixstore', 'shopify'). */
    public function platform(): string;

    /**
     * Health-check de conectividad/credenciales contra la tienda.
     * @return array{ok:bool,status:int,data:mixed,error:?string}
     */
    public function ping(): array;

    /**
     * Lista productos/artículos del catálogo (con filtros/paginación de la plataforma).
     * @param array<string,mixed> $filters
     * @return array{ok:bool,status:int,data:mixed,error:?string}
     */
    public function getProducts(array $filters = []): array;

    /**
     * Obtiene un producto por su identificador.
     * @return array{ok:bool,status:int,data:mixed,error:?string}
     */
    public function getProduct(string|int $id, array $params = []): array;

    /**
     * Lista pedidos/órdenes (filtros típicos: from, to, status, page, limit).
     * @param array<string,mixed> $filters
     * @return array{ok:bool,status:int,data:mixed,error:?string}
     */
    public function getOrders(array $filters = []): array;

    /**
     * Obtiene una orden por su identificador.
     * @return array{ok:bool,status:int,data:mixed,error:?string}
     */
    public function getOrder(string|int $id, array $params = []): array;

    /**
     * Lista clientes de la tienda.
     * @param array<string,mixed> $filters
     * @return array{ok:bool,status:int,data:mixed,error:?string}
     */
    public function getCustomers(array $filters = []): array;

    /**
     * GET crudo a un endpoint de la plataforma (path relativo a la base de la API).
     * @param array<string,mixed> $query
     * @return array{ok:bool,status:int,data:mixed,error:?string}
     */
    public function get(string $path, array $query = []): array;

    /**
     * POST crudo a un endpoint de la plataforma.
     * @param array<string,mixed> $body
     * @param array<string,mixed> $query
     * @return array{ok:bool,status:int,data:mixed,error:?string}
     */
    public function post(string $path, array $body = [], array $query = []): array;
}
