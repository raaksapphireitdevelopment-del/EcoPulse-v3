<?php
declare(strict_types=1);

namespace EcoPulse\Core;

/**
 * Encapsulates and sanitizes incoming HTTP requests
 */
class Request
{
    private string $method;
    private string $uri;
    private array $queryParams;
    private array $body;
    private array $headers;
    private array $files;
    private ?array $user = null;
    private ?array $organization = null;
    private ?string $orgRole = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $parsedUrl = parse_url($requestUri);
        $rawPath = '/' . trim($parsedUrl['path'] ?? '/', '/');
        if (str_contains($rawPath, 'index.php')) {
            $rawPath = substr($rawPath, strpos($rawPath, 'index.php') + strlen('index.php'));
            if ($rawPath === '' || $rawPath === false) {
                $rawPath = '/';
            }
        }
        $this->uri = '/' . trim($rawPath, '/');

        $this->queryParams = $_GET;
        $this->headers = $this->extractHeaders();
        $this->files = $_FILES;
        $this->body = $this->parseRequestBody();
    }

    private function extractHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$header] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'AUTHORIZATION'], true)) {
                $header = str_replace('_', '-', strtolower($key));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }

    private function parseRequestBody(): array
    {
        if (in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $contentType = $this->getHeader('content-type') ?? '';

            if (str_contains($contentType, 'application/json')) {
                $rawInput = file_get_contents('php://input');
                if (!empty($rawInput)) {
                    $decoded = json_decode($rawInput, true);
                    return is_array($decoded) ? $decoded : [];
                }
            }

            return $_POST;
        }

        return [];
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getHeader(string $name): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? null;
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function getAllQuery(): array
    {
        return $this->queryParams;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->queryParams);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->queryParams[$key] ?? $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        $val = $this->get($key, $default);
        return is_string($val) ? trim($val) : (string)$val;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $val = $this->get($key, $default);
        return is_numeric($val) ? (int)$val : $default;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $val = $this->get($key, $default);
        return is_numeric($val) ? (float)$val : $default;
    }

    public function getArray(string $key, array $default = []): array
    {
        $val = $this->get($key, $default);
        return is_array($val) ? $val : $default;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->body);
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getFile(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && is_array($this->files[$key]) && ($this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    public function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Only inspect forwarded headers if explicitly configured trusted proxies are set
        $trustedProxiesEnv = getenv('TRUSTED_PROXIES');
        if (!empty($trustedProxiesEnv)) {
            $trustedProxies = array_map('trim', explode(',', $trustedProxiesEnv));
            if (in_array($remoteAddr, $trustedProxies, true)) {
                $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
                if (!empty($forwarded)) {
                    // Extract leftmost client IP from comma-separated list
                    $ips = array_map('trim', explode(',', $forwarded));
                    $clientCandidate = $ips[0] ?? '';
                    if (filter_var($clientCandidate, FILTER_VALIDATE_IP)) {
                        return $clientCandidate;
                    }
                }
            }
        }

        // Default: strictly trust REMOTE_ADDR to prevent header-spoofing attacks
        if (filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return $remoteAddr;
        }

        return '127.0.0.1';
    }

    public function getIp(): string
    {
        return $this->getClientIp();
    }

    public function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?array
    {
        return $this->user;
    }

    public function setOrganization(?array $org, ?string $role = null): void
    {
        $this->organization = $org;
        $this->orgRole = $role;
    }

    public function getOrganization(): ?array
    {
        return $this->organization;
    }

    public function getOrgRole(): ?string
    {
        return $this->orgRole;
    }

    public function getMemberRole(): ?string
    {
        return $this->orgRole;
    }
}

