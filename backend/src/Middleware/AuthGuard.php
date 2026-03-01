<?php

declare(strict_types=1);

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class AuthGuard
{
    /**
     * Verify JWT from Authorization header.
     * Returns decoded payload on success, or sends 401 and exits.
     *
     * @return object  Decoded JWT payload
     */
    public static function verify(): object
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            self::unauthorized('Authorization token not provided');
        }

        $token  = substr($authHeader, 7);
        $secret = $_ENV['JWT_SECRET'] ?? 'slipscan_secret';

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return $decoded;
        } catch (ExpiredException) {
            self::unauthorized('Token has expired');
        } catch (SignatureInvalidException) {
            self::unauthorized('Invalid token signature');
        } catch (\Exception $e) {
            self::unauthorized('Invalid token');
        }
    }

    /**
     * Send 401 Unauthorized and exit
     */
    private static function unauthorized(string $message): never
    {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
