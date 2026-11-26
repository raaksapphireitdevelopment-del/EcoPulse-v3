<?php
declare(strict_types=1);

namespace EcoPulse\Middleware;

use EcoPulse\Core\Auth;
use EcoPulse\Core\Request;
use EcoPulse\Core\Response;
use EcoPulse\Core\Session;

class CsrfMiddleware
{
    public function __invoke(Request $request): ?bool
    {
        $method = $request->getMethod();
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return null; // Safe read methods
        }

        $uri = $request->getUri();
        // Skip public unauthenticated endpoints
        $publicEndpoints = ['/api/auth/login', '/api/auth/register', '/api/health'];
        foreach ($publicEndpoints as $endpoint) {
            if (str_ends_with($uri, $endpoint)) {
                return null;
            }
        }

        // Only enforce for authenticated sessions
        if (!Auth::check()) {
            return null; // Let AuthMiddleware handle 401
        }

        $token = $request->getHeader('x-csrf-token') ?? $request->getString('csrf_token');

        if (empty($token) || !Session::validateCsrfToken($token)) {
            Response::error('Invalid or missing CSRF token.', 403, 'CSRF_TOKEN_INVALID');
            return false;
        }

        return null;
    }
}
