<?php
declare(strict_types=1);

namespace EcoPulse\Middleware;

use EcoPulse\Core\Request;
use EcoPulse\Core\Response;

class CorsMiddleware
{
    public function __invoke(Request $request): ?bool
    {
        $cors = require __DIR__ . '/../config/cors.php';
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        $isAllowed = false;
        $matchedOrigin = '';

        if (!empty($origin)) {
            foreach ($cors['allowed_origins'] as $allowed) {
                if ($origin === $allowed || str_starts_with($origin, 'http://localhost') || str_starts_with($origin, 'http://127.0.0.1')) {
                    $isAllowed = true;
                    $matchedOrigin = $origin;
                    break;
                }
            }
        }

        if ($isAllowed) {
            header("Access-Control-Allow-Origin: {$matchedOrigin}");
            if ($cors['allow_credentials']) {
                header('Access-Control-Allow-Credentials: true');
            }
        } elseif (!$cors['allow_credentials']) {
            header("Access-Control-Allow-Origin: *");
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $cors['allowed_methods']));
        header('Access-Control-Allow-Headers: ' . implode(', ', $cors['allowed_headers']));
        header('Access-Control-Expose-Headers: ' . implode(', ', $cors['exposed_headers']));
        header('Access-Control-Max-Age: ' . $cors['max_age']);

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        return null;
    }
}
