<?php

declare(strict_types=1);

use App\Controllers\AuthController;

/**
 * Auth Routes
 *
 * POST /api/auth/register
 * POST /api/auth/login
 * POST /api/auth/logout
 * GET  /api/auth/me
 */
function handleAuthRoutes(string $method, string $subPath, array $body): void
{
    $controller = new AuthController();

    match (true) {
        $method === 'POST' && $subPath === '/register' => $controller->register($body),
        $method === 'POST' && $subPath === '/login'    => $controller->login($body),
        $method === 'POST' && $subPath === '/logout'   => $controller->logout(),
        $method === 'GET'  && $subPath === '/me'       => $controller->me(),
        default => routeNotFound(),
    };
}
