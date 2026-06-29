<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

/**
 * Driver de la REST API v3 de **WooCommerce** (WordPress).
 *
 * Auth: HTTP Basic con consumer_key / consumer_secret (sobre HTTPS).
 * Base: https://{site}/wp-json/wc/v3
 *
 * Credenciales esperadas:
 *   site             → URL del sitio WordPress (p. ej. https://mitienda.com)
 *   consumer_key     → ck_...
 *   consumer_secret  → cs_...
 *
 * Doc oficial: https://woocommerce.github.io/woocommerce-rest-api-docs/
 */
final class WooCommerceConnector extends AbstractStoreConnector
{
    public function platform(): string
    {
        return 'woocommerce';
    }

    protected function apiBase(): string
    {
        $site = $this->baseUrl !== '' ? $this->baseUrl : rtrim((string) ($this->credentials['site'] ?? ''), '/');
        return $site . '/wp-json/wc/v3';
    }

    /** @return array<int,string> */
    protected function authHeaders(): array
    {
        $key    = (string) ($this->credentials['consumer_key'] ?? '');
        $secret = (string) ($this->credentials['consumer_secret'] ?? '');
        return ['Authorization: Basic ' . base64_encode($key . ':' . $secret)];
    }

    public function ping(): array
    {
        // El índice del namespace responde 200 con credenciales válidas.
        return $this->get('');
    }

    public function getProducts(array $filters = []): array
    {
        return $this->get('products', $filters);
    }

    public function getProduct(string|int $id, array $params = []): array
    {
        return $this->get('products/' . $id, $params);
    }

    public function getOrders(array $filters = []): array
    {
        return $this->get('orders', $filters);
    }

    public function getOrder(string|int $id, array $params = []): array
    {
        return $this->get('orders/' . $id, $params);
    }

    public function getCustomers(array $filters = []): array
    {
        return $this->get('customers', $filters);
    }
}
