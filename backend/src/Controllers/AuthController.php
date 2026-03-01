<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthGuard;
use Firebase\JWT\JWT;

class AuthController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // --------------------------------------------------------
    // POST /api/auth/register
    // --------------------------------------------------------
    public function register(array $body): void
    {
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        // Validate input
        if (empty($email) || empty($password)) {
            $this->respond(400, ['success' => false, 'message' => 'Email and password are required']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond(400, ['success' => false, 'message' => 'Invalid email format']);
            return;
        }

        if (strlen($password) < 8) {
            $this->respond(400, ['success' => false, 'message' => 'Password must be at least 8 characters']);
            return;
        }

        // Check duplicate email
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $this->respond(409, ['success' => false, 'message' => 'Email already registered']);
            return;
        }

        // Hash password with bcrypt (cost 12)
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Insert user
        $stmt = $this->db->prepare('INSERT INTO users (email, password, role) VALUES (?, ?, ?)');
        $stmt->execute([$email, $hashedPassword, 'user']);
        $userId = (int) $this->db->lastInsertId();

        $this->respond(201, [
            'success' => true,
            'message' => 'User registered successfully',
            'user'    => [
                'id'    => $userId,
                'email' => $email,
                'role'  => 'user',
            ],
        ]);
    }

    // --------------------------------------------------------
    // POST /api/auth/login
    // --------------------------------------------------------
    public function login(array $body): void
    {
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->respond(400, ['success' => false, 'message' => 'Email and password are required']);
            return;
        }

        // Find user
        $stmt = $this->db->prepare('SELECT id, email, password, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $this->respond(401, ['success' => false, 'message' => 'Invalid email or password']);
            return;
        }

        // Generate JWT
        $secret  = $_ENV['JWT_SECRET'] ?? 'slipscan_secret';
        $expire  = (int) ($_ENV['JWT_EXPIRE'] ?? 86400);
        $now     = time();

        $payload = [
            'iss'   => 'slipscan',
            'iat'   => $now,
            'exp'   => $now + $expire,
            'sub'   => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];

        $token = JWT::encode($payload, $secret, 'HS256');

        $this->respond(200, [
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'    => (int) $user['id'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]);
    }

    // --------------------------------------------------------
    // POST /api/auth/logout
    // --------------------------------------------------------
    public function logout(): void
    {
        // JWT is stateless; client must discard the token
        $this->respond(200, [
            'success' => true,
            'message' => 'Logged out successfully. Please remove the token on client side.',
        ]);
    }

    // --------------------------------------------------------
    // GET /api/auth/me  (protected)
    // --------------------------------------------------------
    public function me(): void
    {
        $decoded = AuthGuard::verify();

        // Re-fetch fresh user data from DB
        $stmt = $this->db->prepare('SELECT id, email, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$decoded->sub]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->respond(404, ['success' => false, 'message' => 'User not found']);
            return;
        }

        $this->respond(200, [
            'success' => true,
            'user'    => [
                'id'         => (int) $user['id'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'created_at' => $user['created_at'],
            ],
        ]);
    }

    // --------------------------------------------------------
    // Helper: send JSON response
    // --------------------------------------------------------
    private function respond(int $code, array $data): void
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
