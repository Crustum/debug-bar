<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Http\Client\ClientEvent;
use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Service\CallsiteCapture;
use Crustum\DebugBar\Service\QueryAnalyzer;
use Crustum\DebugBar\Service\RedactionService;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * HTTP client panel.
 *
 * Instruments every application `Cake\Http\Client` through the global
 * `HttpClient.beforeSend` / `HttpClient.afterSend` events: no client-side
 * changes needed. Requests that never complete (exceptions) stay pending
 * and are reported as failed with no response; a response without a real
 * HTTP status counts as failed too. Bodies are truncated at capture time;
 * only shapes and counts reach the stored row. Header values under
 * sensitive names (`authorization`, `token`, `secret`, …) are masked via
 * `RedactionService::maskSensitive`, so stored rows never carry cleartext
 * credentials into the inspector or the safe-cURL copy.
 */
class HttpClientPanel extends DebugPanel
{
    /**
     * Pending/completed captures keyed by request object id.
     *
     * @var array<int, array<string, mixed>>
     */
    protected static array $inbox = [];

    /**
     * Subscribes to HTTP client request events once per manager.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->subscribeOnce(EventManager::instance(), [
            'HttpClient.beforeSend' => $this->captureSend(...),
            'HttpClient.afterSend' => $this->captureResponse(...),
        ]);
    }

    /**
     * Records the outgoing request with its start time.
     *
     * @param \Cake\Event\EventInterface $event Client event with `request`.
     * @return void
     */
    public function captureSend(EventInterface $event): void
    {
        $request = $event->getData('request');
        if (!$request instanceof RequestInterface) {
            return;
        }

        $redaction = new RedactionService((array)Configure::read('DebugBar'));
        $maxString = $redaction->maxStringLength();
        $uri = $request->getUri();
        $body = '';
        try {
            $body = (string)$request->getBody();
        } catch (Throwable) {
            $body = '';
        }

        static::$inbox[spl_object_id($request)] = [
            'method' => $request->getMethod(),
            'scheme' => $uri->getScheme(),
            'host' => $uri->getHost(),
            'port' => $uri->getPort(),
            'path' => $uri->getPath(),
            'query' => $uri->getQuery(),
            'request_headers' => $redaction->maskSensitive($this->headers($request)),
            'request_body' => mb_strcut($body, 0, $maxString),
            'request_truncated' => strlen($body) > $maxString,
            'start' => microtime(true),
            'source' => CallsiteCapture::label(CallsiteCapture::capture([__FILE__])['callsite']),
            'stack' => $this->appStack(),
            'response' => null,
        ];
    }

    /**
     * Attaches the response to its pending request.
     *
     * @param \Cake\Event\EventInterface $event Client event with `request` and `response`.
     * @return void
     */
    public function captureResponse(EventInterface $event): void
    {
        $request = $event->getData('request');
        if (!is_object($request)) {
            return;
        }

        $id = spl_object_id($request);
        if (!isset(static::$inbox[$id])) {
            return;
        }

        $redaction = new RedactionService((array)Configure::read('DebugBar'));
        $maxString = $redaction->maxStringLength();

        $response = $event instanceof ClientEvent
            ? $event->getResult()
            : $event->getData('response');
        $status = 0;
        $responseHeaders = [];
        $responseBody = '';
        if ($response instanceof ResponseInterface) {
            $status = $response->getStatusCode();
            $responseHeaders = $redaction->maskSensitive($this->headerMap($response->getHeaders()));
            try {
                $responseBody = (string)$response->getBody();
            } catch (Throwable) {
                $responseBody = '';
            }
        }

        static::$inbox[$id]['response'] = [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => mb_strcut($responseBody, 0, $maxString),
            'truncated' => strlen($responseBody) > $maxString,
        ];
        static::$inbox[$id]['duration_ms'] = max(0.0, (microtime(true) - (float)static::$inbox[$id]['start']) * 1000);
    }

    /**
     * Resets captured requests (long-lived workers and tests).
     *
     * @return void
     */
    public static function clear(): void
    {
        static::$inbox = [];
    }

    /**
     * Get the data for this panel.
     *
     * Presents one row per captured request: formatted duration, status
     * label (`200 OK` / `Failed`), content type and response size, plus the
     * application source and stack captured at send time.
     *
     * @return array
     */
    public function data(): array
    {
        $slowMs = max(0.0, (float)Configure::read('DebugBar.slow_http_request_ms', 250));
        $requests = [];
        foreach (array_values(static::$inbox) as $index => $entry) {
            $entry = (array)$entry;
            $response = is_array($entry['response'] ?? null) ? $entry['response'] : null;
            $status = $response !== null ? (int)$response['status'] : 0;

            $failed = $response === null || $status <= 0;
            $duration = (float)($entry['duration_ms'] ?? 0);
            $slow = !$failed && $slowMs > 0 && $duration >= $slowMs;
            $errorStatus = !$failed && $status >= 400;
            $responseHeaders = $response !== null ? (array)($response['headers'] ?? []) : [];
            $responseBody = $response !== null ? (string)($response['body'] ?? '') : '';
            $requestHeaders = (array)($entry['request_headers'] ?? []);
            $requestBody = (string)($entry['request_body'] ?? '');
            $requestPretty = $this->prettyBody($requestBody, $this->contentType($requestHeaders));
            $responsePretty = $this->prettyBody($responseBody, $this->contentType($responseHeaders));
            $source = (string)($entry['source'] ?? '');
            $stack = array_values((array)($entry['stack'] ?? []));
            $method = (string)($entry['method'] ?? 'GET');
            $url = $this->url($entry);
            $requests[] = $entry + [
                'key' => 'http-' . ($index + 1),
                'execution' => $index + 1,
                'failed' => $failed,
                'slow' => $slow,
                'attention' => $failed || $slow || $errorStatus,
                'status' => $failed ? 0 : $status,
                'status_label' => $failed ? 'Failed' : $this->statusLabel($status),
                'list_status_label' => $failed ? 'Failed' : (string)$status,
                'duration_ms' => $duration,
                'duration_label' => QueryAnalyzer::formatDuration($duration),
                'timing_summary' => number_format($duration, 2) . ' ms',
                'content_type' => $this->contentType($responseHeaders),
                'response_size' => strlen($responseBody),
                'response_size_label' => $this->sizeLabel(strlen($responseBody)),
                'request_body_size_label' => $requestBody !== '' ? $this->sizeLabel(strlen($requestBody)) : '—',
                'response_body_size_label' => $responseBody !== '' ? $this->sizeLabel(strlen($responseBody)) : '—',
                'request_body_pretty' => $requestPretty['content'],
                'request_body_language' => $requestPretty['language'],
                'response_body_pretty' => $responsePretty['content'],
                'response_body_language' => $responsePretty['language'],
                'url' => $url,
                'search' => strtolower($method . ' ' . $url . ' ' . ($failed ? 'failed' : (string)$status)),
                'callsite_label' => $source !== '' ? $source : null,
                'has_source' => $source !== '' || $stack !== [],
                'stack' => $stack,
                'curl' => $this->safeCurl($method, $url, $requestHeaders, $requestBody),
            ];
        }

        static::$inbox = [];

        $slow = 0;
        $failed = 0;
        $totalMs = 0.0;
        foreach ($requests as $request) {
            $slow += (bool)$request['slow'] ? 1 : 0;
            $failed += (bool)$request['failed'] ? 1 : 0;
            $totalMs += (float)$request['duration_ms'];
        }

        return [
            'requests' => $requests,
            'summary' => [
                'count' => count($requests),
                'slow' => $slow,
                'failed' => $failed,
                'duration_ms' => $totalMs,
                'total_ms' => $totalMs,
                'total_label' => QueryAnalyzer::formatDuration($totalMs),
            ],
        ];
    }

    /**
     * Get the summary data.
     *
     * @return string
     */
    public function summary(): string
    {
        return (string)count(static::$inbox);
    }

    /**
     * Builds the full request URL from its captured parts.
     *
     * @param array<string, mixed> $entry Captured request entry.
     * @return string Absolute URL without credentials.
     */
    protected function url(array $entry): string
    {
        $url = ($entry['scheme'] ?? 'https') . '://' . ($entry['host'] ?? '');
        $port = $entry['port'] ?? null;
        if (is_int($port) && $port > 0) {
            $url .= ':' . $port;
        }

        $url .= (string)($entry['path'] ?? '');
        $query = (string)($entry['query'] ?? '');
        if ($query !== '') {
            $url .= '?' . $query;
        }

        return $url;
    }

    /**
     * Formats a status code with its reason phrase (`200 OK`).
     *
     * Unknown codes render as the bare number — never guessed.
     *
     * @param int $status HTTP status code.
     * @return string Status label.
     */
    protected function statusLabel(int $status): string
    {
        $phrase = static::reasonPhrase($status);

        return $phrase !== '' ? $status . ' ' . $phrase : (string)$status;
    }

    /**
     * Reads the response content type without parameters.
     *
     * @param array<string, string> $headers Response header map.
     * @return string Bare MIME type or empty.
     */
    protected function contentType(array $headers): string
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string)$name) !== 'content-type') {
                continue;
            }

            $parts = explode(';', (string)$value, 2);

            return trim($parts[0]);
        }

        return '';
    }

    /**
     * Pretty-prints a body for the evidence block.
     *
     * JSON payloads render indented like SQL evidence does; anything else
     * passes through untouched. Invalid JSON is never "fixed" — the raw
     * text is shown so a broken payload stays visible.
     *
     * @param string $body Raw body text.
     * @param string $contentType Bare MIME type.
     * @return array{content: string, language: string}
     */
    protected function prettyBody(string $body, string $contentType): array
    {
        if ($body === '' || stripos($contentType, 'json') === false) {
            return ['content' => $body, 'language' => 'http'];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ['content' => $body, 'language' => 'http'];
        }

        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($pretty === false) {
            return ['content' => $body, 'language' => 'http'];
        }

        return ['content' => $pretty, 'language' => 'json'];
    }

    /**
     * Formats a byte count for the status line (`95 B`, `44.5 KB`).
     *
     * @param int $bytes Body length in bytes.
     * @return string Human-readable size.
     */
    protected function sizeLabel(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / 1024 / 1024, 1) . ' MB';
    }

    /**
     * Captures the short application stack behind the send call.
     *
     * @return array<int, array{file: string|null, line: int|null, function: string, app: bool}>
     */
    protected function appStack(): array
    {
        $capture = CallsiteCapture::capture([__FILE__]);

        return array_values(array_filter(
            $capture['stack'],
            static fn(array $frame): bool => $frame['app'] === true,
        ));
    }

    /**
     * Builds a safe cURL command reproducing the request.
     *
     * Credential headers are replaced with `[redacted]` — the command
     * shows the shape, then the developer restores local auth.
     *
     * @param string $method HTTP method.
     * @param string $url Absolute URL.
     * @param array<string, string> $headers Request header map.
     * @param string $body Raw request body.
     * @return string Copy-pasteable command.
     */
    protected function safeCurl(string $method, string $url, array $headers, string $body): string
    {
        $parts = ['curl', '-X', strtoupper($method), "'" . str_replace("'", "'\\''", $url) . "'"];
        foreach ($headers as $name => $value) {
            $lower = strtolower((string)$name);
            $safe = $value;
            if (
                in_array($lower, ['authorization', 'cookie', 'set-cookie'], true)
                || str_contains($lower, 'token') || str_contains($lower, 'api-key')
                || str_contains($lower, 'apikey') || str_contains($lower, 'secret')
            ) {
                $safe = '[redacted]';
            }

            $parts[] = '-H';
            $parts[] = "'" . str_replace("'", "'\\''", $name . ': ' . $safe) . "'";
        }

        if ($body !== '') {
            $parts[] = '--data';
            $parts[] = "'" . str_replace("'", "'\\''", $body) . "'";
        }

        return implode(' ', $parts);
    }

    /**
     * Reason phrases for the status codes worth naming.
     *
     * @param int $status HTTP status code.
     * @return string Phrase or empty when unknown.
     */
    protected static function reasonPhrase(int $status): string
    {
        $phrases = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];

        return $phrases[$status] ?? '';
    }

    /**
     * Reads request headers as a name/value map.
     *
     * @param \Psr\Http\Message\RequestInterface $request PSR-7 request.
     * @return array<string, string>
     */
    protected function headers(RequestInterface $request): array
    {
        return $this->headerMap($request->getHeaders());
    }

    /**
     * Flattens a PSR-7 header map to name/value lines.
     *
     * @param array<string, mixed> $headers Header map.
     * @return array<string, string>
     */
    protected function headerMap(array $headers): array
    {
        $flat = [];
        foreach ($headers as $name => $values) {
            $flat[(string)$name] = implode(', ', array_map(strval(...), (array)$values));
        }

        return $flat;
    }
}
