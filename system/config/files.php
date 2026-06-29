<?php

declare(strict_types=1);

/**
 * Política del file manager: tamaño máximo de subida y extensiones permitidas.
 */
return [
    'max_upload_bytes' => 5 * 1024 * 1024, // 5 MB
    'allowed_ext' => [
        // imágenes
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg',
        // documentos
        'pdf', 'txt', 'md', 'csv', 'json', 'xml',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        // comprimidos
        'zip', 'rar', '7z',
    ],
];
