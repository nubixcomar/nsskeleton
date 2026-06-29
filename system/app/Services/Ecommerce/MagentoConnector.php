<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

/**
 * Driver de la REST API de **Magento 2** (Adobe Commerce).
 *
 * Auth: Bearer con un Integration Access Token (o token de admin).
 * Base: https://{site}/rest/{store}/V1   (store por defecto: "default")
 *
 * Credenciales esperadas:
 *   site          → URL del sitio Magento (p. ej. https://mitienda.com)
 *   access_token  → integration access token
 *   store_code    → opcional, default "default"
 *
 * Doc oficial: https://developer.adobe.com/commerce/webapi/rest/
 *
 * Nota: los listados de Magento usan `searchCriteria[...]` como query; pasalos
 * ya armados en $filters (p. ej. ['searchCriteria[pageSize]' => 20]).
 */
final class MagentoConnector extends AbstractStoreConnector
{
    public function platform(): string
    {
        return 'magento';
    }

    protected function apiBase(): string
    {
        $site  = $this->baseUrl !== '' ? $this->baseUrl : rtrim((string) ($this->credentials['site'] ?? ''), '/');
        $store = (string) ($this->credentials['store_code'] ?? 'default');
        return $site . '/rest/' . $store . '/V1';
    }

    /** @return array<int,string> */
    protected function authHeaders(): array
    {
        return ['Authorization: Bearer ' . (string) ($this->credentials['access_token'] ?? '')];
    }

    public function ping(): array
    {
        // store/storeConfigs requiere token válido y es liviano.
        return $this->get('store/storeConfigs');
    }

    public function getProducts(array $filters = []): array
    {
        // Magento exige searchCriteria; si no se pasa nada, traemos la primera página.
        if ($filters === []) {
            $filters = ['searchCriteria[pageSize]' => 20, 'searchCriteria[currentPage]' => 1];
        }
        return $this->get('products', $filters);
    }

    public function getProduct(string|int $id, array $params = []): array
    {
        // En Magento el identificador natural del producto es el SKU.
        return $this->get('products/' . rawurlencode((string) $id), $params);
    }

    public function getOrders(array $filters = []): array
    {
        if ($filters === []) {
            $filters = ['searchCriteria[pageSize]' => 20, 'searchCriteria[currentPage]' => 1];
        }
        return $this->get('orders', $filters);
    }

    public function getOrder(string|int $id, array $params = []): array
    {
        return $this->get('orders/' . $id, $params);
    }

    public function getCustomers(array $filters = []): array
    {
        if ($filters === []) {
            $filters = ['searchCriteria[pageSize]' => 20, 'searchCriteria[currentPage]' => 1];
        }
        return $this->get('customers/search', $filters);
    }
}
