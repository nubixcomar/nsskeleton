<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

/**
 * Driver de la Admin REST API de **Shopify**.
 *
 * Auth: header `X-Shopify-Access-Token` (Admin API access token de una app).
 * Base: https://{shop}.myshopify.com/admin/api/{version}
 *
 * Credenciales esperadas:
 *   shop          → subdominio o dominio myshopify (p. ej. "mi-tienda")
 *   access_token  → Admin API access token
 *   api_version   → opcional, p. ej. "2024-04" (default abajo)
 *
 * Doc oficial: https://shopify.dev/docs/api/admin-rest
 */
final class ShopifyConnector extends AbstractStoreConnector
{
    private const DEFAULT_VERSION = '2024-04';

    public function platform(): string
    {
        return 'shopify';
    }

    protected function apiBase(): string
    {
        if ($this->baseUrl !== '') {
            return $this->baseUrl;
        }
        $shop    = (string) ($this->credentials['shop'] ?? '');
        $host    = str_contains($shop, '.') ? $shop : $shop . '.myshopify.com';
        $version = (string) ($this->credentials['api_version'] ?? self::DEFAULT_VERSION);
        return 'https://' . $host . '/admin/api/' . $version;
    }

    /** @return array<int,string> */
    protected function authHeaders(): array
    {
        return ['X-Shopify-Access-Token: ' . (string) ($this->credentials['access_token'] ?? '')];
    }

    public function ping(): array
    {
        return $this->get('shop.json');
    }

    public function getProducts(array $filters = []): array
    {
        return $this->get('products.json', $filters);
    }

    public function getProduct(string|int $id, array $params = []): array
    {
        return $this->get('products/' . $id . '.json', $params);
    }

    public function getOrders(array $filters = []): array
    {
        return $this->get('orders.json', $filters);
    }

    public function getOrder(string|int $id, array $params = []): array
    {
        return $this->get('orders/' . $id . '.json', $params);
    }

    public function getCustomers(array $filters = []): array
    {
        return $this->get('customers.json', $filters);
    }
}
