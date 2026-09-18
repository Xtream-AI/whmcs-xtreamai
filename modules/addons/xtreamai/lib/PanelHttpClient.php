<?php

declare(strict_types=1);

namespace WhmcsXtreamAI;

final class PanelHttpClient
{
    private const USER_AGENT = 'whmcs-xtreamai';
    private const VERSION = '1.7.1';
    private const QUICK_TIMEOUT = 5.0;
    private const QUICK_RETRIES = 0;

    
    private $baseUrl;

    
    private $token;

    
    private $verifySsl;

    
    private $timeout;

    
    private $maxRetries;

    public function __construct(
        string $baseUrl,
        string $token,
        bool $verifySsl = true,
        float $timeout = 30.0,
        int $maxRetries = 3
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->verifySsl = $verifySsl;
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    public static function quick(string $baseUrl, string $token, bool $verifySsl = true): self
    {
        return new self($baseUrl, $token, $verifySsl, self::QUICK_TIMEOUT, self::QUICK_RETRIES);
    }

    

    public function request(
        string $method,
        string $path,
        ?array $query = null,
        ?array $body = null,
        ?string $idempotencyKey = null
    ): array {
        $method = strtoupper($method);

        
        if ($method !== 'GET' && $idempotencyKey === null) {
            $idempotencyKey = bin2hex(random_bytes(16));
        }

        $url = $this->baseUrl . $path;
        if ($query !== null && $query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $rawBody = null;
        if ($body !== null) {
            $rawBody = json_encode(
                $body,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            );
            if ($rawBody === false) {
                throw new PanelApiRequestException('Failed to encode request body as JSON.', 0, 'unknown');
            }
        }

        $headers = $this->buildHeaders($idempotencyKey, $rawBody !== null);

        $attempt = 0;

        while (true) {
            try {
                list($status, $respHeaders, $bodyRaw) = $this->curlRequest($method, $url, $headers, $rawBody);
            } catch (PanelApiRequestException $e) {
                
                if ($e->getErrorType() === 'network' && $attempt < $this->maxRetries) {
                    $attempt++;
                    $this->sleep($this->backoff($attempt));
                    continue;
                }
                throw $e;
            }

            $respHeaders = array_change_key_case($respHeaders, CASE_LOWER);

            $parsedBody = [];
            if ($bodyRaw !== '' && $bodyRaw !== null) {
                $decoded = json_decode($bodyRaw, true);
                if (is_array($decoded)) {
                    $parsedBody = $decoded;
                }
            }

            $shouldRetry = false;
            $waitSeconds = 0.0;
            $slug = isset($parsedBody['error']) ? (string) $parsedBody['error'] : '';

            
            if ($status === 429 && $attempt < $this->maxRetries) {
                $shouldRetry = true;
                $waitSeconds = $this->parseRetryAfterOrBackoff(
                    isset($respHeaders['retry-after']) ? $respHeaders['retry-after'] : null,
                    $this->backoff($attempt + 1)
                );
            } elseif ($status >= 500 && $status < 600 && $attempt < $this->maxRetries) {
                $shouldRetry = true;
                $waitSeconds = $this->backoff($attempt + 1);
            } elseif ($status === 409 && $slug === 'idempotency_in_flight' && $attempt < $this->maxRetries) {
                $shouldRetry = true;
                $waitSeconds = min(pow(2, $attempt), 4);
            }

            if (!$shouldRetry) {
                if ($status >= 200 && $status < 300) {
                    return $parsedBody;
                }
                throw $this->mapException($status, $parsedBody, $respHeaders);
            }

            $attempt++;
            $this->sleep($waitSeconds);
        }
    }

    

    private function buildHeaders(?string $idem, bool $hasBody): array
    {
        $map = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'User-Agent' => self::USER_AGENT . '/' . self::VERSION
                . ' (PHP/' . PHP_VERSION . ')',
        ];
        if ($hasBody) {
            $map['Content-Type'] = 'application/json';
        }
        if ($idem !== null) {
            $map['Idempotency-Key'] = $idem;
        }

        $headers = [];
        foreach ($map as $name => $value) {
            if (preg_match('/[\r\n]/', (string) $name) === 1
                || preg_match('/[\r\n]/', (string) $value) === 1) {
                throw new \InvalidArgumentException('Illegal CR/LF in HTTP header.');
            }
            $headers[] = $name . ': ' . $value;
        }

        return $headers;
    }

    

    private function curlRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => (int) ceil($this->timeout),
            CURLOPT_TIMEOUT => (int) ceil($this->timeout * 2),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
        ]);

        if (!$this->verifySsl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);

        if ($errno !== 0) {
            $message = curl_error($ch);
            curl_close($ch);

            throw new PanelApiRequestException(
                'cURL error #' . $errno . ': ' . $message,
                0,
                'network'
            );
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerBlob = substr((string) $raw, 0, $headerSize);
        $bodyStr = (string) substr((string) $raw, $headerSize);

        $parsedHeaders = [];
        foreach (preg_split('/\r?\n/', $headerBlob) as $line) {
            if (strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                $parsedHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        return [$status, $parsedHeaders, $bodyStr];
    }

    

    private function mapException(int $status, array $body, array $headers): PanelApiRequestException
    {
        $slug = isset($body['error']) ? (string) $body['error'] : 'unknown';
        $message = isset($body['message']) ? trim((string) $body['message']) : '';
        if ($message === '') {
            $message = 'HTTP ' . $status;
        }

        $errorType = $this->mapErrorType($status, $slug);

        $retryAfter = (int) $this->parseRetryAfterOrBackoff(
            isset($headers['retry-after']) ? $headers['retry-after'] : null,
            60
        );

        if ($errorType === 'rate_limit' && $retryAfter > 0) {
            $message .= ' (retry after ' . $retryAfter . 's)';
        }

        return new PanelApiRequestException($message, $status, $errorType);
    }

    

    private function mapErrorType(int $status, string $slug): string
    {
        if ($status === 422 && $slug === 'insufficient_slots') {
            return 'insufficient_credits';
        }

        switch (true) {
            case $status === 400:
                return 'bad_request';
            case $status === 401:
                return 'authentication';
            case $status === 402:
                return 'insufficient_credits';
            case $status === 403:
                return 'authorization';
            case $status === 404:
                return 'not_found';
            case $status === 409:
                return 'conflict';
            case $status === 422:
                return 'validation';
            case $status === 429:
                return 'rate_limit';
            case $status === 503:
                return 'service_unavailable';
            case $status >= 500:
                return 'server';
            default:
                return 'unknown';
        }
    }

    

    private function backoff(int $attempt): float
    {
        $base = min(pow(2, $attempt), 30);

        return $base + (mt_rand(0, 500) / 1000.0);
    }

    

    private function parseRetryAfterOrBackoff(?string $header, float $backoff): float
    {
        if ($header === null) {
            return $backoff;
        }
        $trimmed = trim($header);
        if ($trimmed === '' || !ctype_digit($trimmed)) {
            return $backoff;
        }
        $n = (int) $trimmed;
        if ($n <= 0) {
            return $backoff;
        }

        return (float) min($n, 60);
    }

    private function sleep(float $seconds): void
    {
        usleep((int) ($seconds * 1000000));
    }
}
