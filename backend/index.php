<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Routes/auth.php';

use Dotenv\Dotenv;

// ── Load .env ──────────────────────────────────────────────────────────────
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ── CORS Headers ───────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Parse Request ──────────────────────────────────────────────────────────
$method   = $_SERVER['REQUEST_METHOD'];
$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body     = json_decode(file_get_contents('php://input'), true) ?? [];

// Strip trailing slash
$uri = rtrim($uri, '/') ?: '/';

// ── Route Dispatcher ───────────────────────────────────────────────────────
if (str_starts_with($uri, '/api/auth')) {
    $subPath = substr($uri, strlen('/api/auth')) ?: '/';
    handleAuthRoutes($method, $subPath, $body);
} else {
    routeNotFound();
}

// ── 404 Helper ─────────────────────────────────────────────────────────────
function routeNotFound(): void
{
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Route not found',
    ]);
}
