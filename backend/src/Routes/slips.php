<?php

declare(strict_types=1);

use App\Controllers\SlipController;

/**
 * Slip Routes
 *
 * POST /api/slips/upload
 * POST /api/slips/upload-batch
 * GET  /api/slips
 * GET  /api/slips/:id
 */
function handleSlipRoutes(string $method, string $subPath, array $body): void
{
    $controller = new SlipController();

    // Match /api/slips/:id  (e.g. /42)
    if ($method === 'GET' && preg_match('#^/(\d+)$#', $subPath, $m)) {
        $controller->getById((int) $m[1]);
        return;
    }

    match (true) {
        $method === 'POST' && $subPath === '/upload'       => $controller->upload(),
        $method === 'POST' && $subPath === '/upload-batch' => $controller->uploadBatch(),
        $method === 'GET'  && $subPath === ''              => $controller->listAll(),
        $method === 'GET'  && $subPath === '/'             => $controller->listAll(),
        default => routeNotFound(),
    };
}
