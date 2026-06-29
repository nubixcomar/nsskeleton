<?php

declare(strict_types=1);

/**
 * Tipos de usuario del sistema (login unificado). Cada app derivada puede sumar
 * los suyos (clientes, depósito, vendedores, etc.). Clave => etiqueta visible.
 * El `user_type` identifica qué clase de usuario es; el `role` define permisos.
 */
return [
    'admin'    => 'Administrador',
    'cliente'  => 'Cliente',
    'deposito' => 'Depósito',
    'vendedor' => 'Vendedor',
];
