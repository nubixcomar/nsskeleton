<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

/**
 * Driver de la API v1 de **Tienda Nube / Nuvemshop**.
 *
 * Auth: header `Authentication: bearer {access_token}` + `User-Agent` obligatorio.
 * Base: https://api.tiendanube.com/v1/{store_id}
 *
 * Credenciales esperadas:
 *   store_id      → id numérico de la tienda
 *   access_token  → token de la app autorizada
 *   user_agent    → opcional, identifica tu app (recomendado por TN)
 *
 * Doc oficial: https://tiendanube.github.io/api-documentation/
 */
final class TiendaNubeConnector extends AbstractStoreConnector
{
    public function platform(): string
    {
        return 'tiendanube';
    }

    protected function apiBase(): string
    {
        if ($this->baseUrl !== '') {
            return $this->baseUrl;
        }
        $storeId = (string) ($this->credentials['store_id'] ?? '');
        return 'https://api.tiendanube.com/v1/' . $storeId;
    }

    /** @return array<int,string> */
    protected function authHeaders(): array
    {
        $ua = (string) ($this->credentials['user_agent'] ?? 'nsSkeleton (ecommerce@nubixstore)');
        return [
            'Authentication: bearer ' . (string) ($this->credentials['access_token'] ?? ''),
            'User-Agent: ' . $ua,
        ];
    }

    public function ping(): array
    {
        // No hay endpoint health dedicado: una llamada liviana al store sirve de ping.
        return $this->get('store');
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
