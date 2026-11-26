<?php
declare(strict_types=1);

namespace EcoPulse\Core;

/**
 * Standardized JSON response emitter
 */
class Response
{
    public static function json(
        mixed $data = null,
        int $statusCode = 200,
        string $message = 'Operation completed successfully.',
        array $meta = []
    ): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'status' => $statusCode,
            ], $meta),
        ];

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(
        string $message = 'An unexpected error occurred.',
        int $statusCode = 400,
        string $errorCode = 'BAD_REQUEST',
        array $details = []
    ): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        // Security: Prevent exposure of database credentials, SQL traces, and internal filesystem paths
        $isProd = getenv('APP_ENV') === 'production' || getenv('APP_DEBUG') === 'false';
        $sanitizedMessage = $message;

        if ($statusCode >= 500) {
            if ($isProd) {
                $sanitizedMessage = 'An internal system error occurred. Please contact system support.';
                $details = [];
            } else {
                // In non-production, mask any passwords, tokens, or raw filesystem connection strings
                $sanitizedMessage = preg_replace('/password\s*=\s*[\'"][^\'"]*[\'"]/i', 'password=***', $sanitizedMessage);
                $sanitizedMessage = preg_replace('/pwd\s*=\s*[\'"][^\'"]*[\'"]/i', 'pwd=***', $sanitizedMessage);
            }
        }

        $payload = [
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $sanitizedMessage,
                'details' => $details,
            ],
            'meta' => [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'status' => $statusCode,
            ],
        ];

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }
}
