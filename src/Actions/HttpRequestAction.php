<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class HttpRequestAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'http_request';
    }

    public function getName(): string
    {
        return 'HTTP Request';
    }

    public function getDescription(): string
    {
        return 'Make an HTTP request to an external API';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $url = $config['url'] ?? null;
        $method = strtoupper($config['method'] ?? 'GET');
        $body = $config['body'] ?? null;
        $query = $config['query'] ?? [];
        $headers = $config['headers'] ?? [];
        $timeout = $config['timeout'] ?? config('workflow-conductor.actions.http_request.timeout', 30);
        $responseKey = $config['response_key'] ?? 'http_response';
        $auth = $config['auth'] ?? null;

        if (empty($url)) {
            return ActionResult::failure('No URL specified');
        }

        // Check allowed hosts
        $allowedHosts = config('workflow-conductor.actions.http_request.allowed_hosts', ['*']);
        if (! $this->isHostAllowed($url, $allowedHosts)) {
            return ActionResult::failure('Host not in allowed hosts list');
        }

        try {
            $client = new Client([
                'timeout' => $timeout,
            ]);

            $options = [
                'headers' => array_merge([
                    'Accept' => 'application/json',
                    'User-Agent' => 'Laravel-Workflows/1.0',
                ], $headers),
            ];

            if (! empty($query)) {
                $options['query'] = $query;
            }

            if ($auth !== null) {
                $this->applyAuth($options, $auth);
            }

            if (in_array($method, ['POST', 'PUT', 'PATCH']) && $body !== null) {
                if (is_array($body)) {
                    $options['json'] = $body;
                } else {
                    $options['body'] = $body;
                }
            }

            $response = $client->request($method, $url, $options);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $responseHeaders = $response->getHeaders();

            // Try to parse JSON response
            $responseData = json_decode($responseBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $responseData = $responseBody;
            }

            return ActionResult::success('HTTP request successful', [
                $responseKey => $responseData,
                'status_code' => $statusCode,
                'response_headers' => $responseHeaders,
            ]);

        } catch (RequestException $e) {
            $response = $e->getResponse();
            $statusCode = $response?->getStatusCode();
            $body = $response?->getBody()->getContents();

            return ActionResult::failure(
                "HTTP request failed with status {$statusCode}: " . $e->getMessage(),
                $e,
                [
                    'status_code' => $statusCode,
                    'response' => $body,
                ]
            );
        } catch (\Throwable $e) {
            return ActionResult::failure('HTTP request failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Check if the host is in the allowed list.
     *
     * @param array<string> $allowedHosts
     */
    protected function isHostAllowed(string $url, array $allowedHosts): bool
    {
        if (in_array('*', $allowedHosts)) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null) {
            return false;
        }

        foreach ($allowedHosts as $allowedHost) {
            if ($host === $allowedHost) {
                return true;
            }

            // Support wildcard subdomains
            if (str_starts_with($allowedHost, '*.')) {
                $domain = substr($allowedHost, 2);
                if (str_ends_with($host, $domain) || $host === $domain) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Apply authentication to request options.
     *
     * @param array<string, mixed> $options
     * @param array<string, mixed> $auth
     */
    protected function applyAuth(array &$options, array $auth): void
    {
        $type = $auth['type'] ?? 'basic';

        match ($type) {
            'basic' => $options['auth'] = [$auth['username'] ?? '', $auth['password'] ?? ''],
            'bearer' => $options['headers']['Authorization'] = 'Bearer ' . ($auth['token'] ?? ''),
            'api_key' => $this->applyApiKeyAuth($options, $auth),
            default => null,
        };
    }

    /**
     * Apply API key authentication.
     *
     * @param array<string, mixed> $options
     * @param array<string, mixed> $auth
     */
    protected function applyApiKeyAuth(array &$options, array $auth): void
    {
        $key = $auth['key'] ?? 'api_key';
        $value = $auth['value'] ?? '';
        $location = $auth['location'] ?? 'header';

        if ($location === 'header') {
            $options['headers'][$key] = $value;
        } elseif ($location === 'query') {
            $options['query'][$key] = $value;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'The request URL',
                    'required' => true,
                ],
                'method' => [
                    'type' => 'string',
                    'description' => 'HTTP method',
                    'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                    'default' => 'GET',
                ],
                'body' => [
                    'type' => 'mixed',
                    'description' => 'Request body (for POST/PUT/PATCH)',
                ],
                'query' => [
                    'type' => 'object',
                    'description' => 'Query string parameters',
                ],
                'headers' => [
                    'type' => 'object',
                    'description' => 'HTTP headers',
                ],
                'auth' => [
                    'type' => 'object',
                    'description' => 'Authentication configuration',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['basic', 'bearer', 'api_key']],
                        'username' => ['type' => 'string'],
                        'password' => ['type' => 'string'],
                        'token' => ['type' => 'string'],
                        'key' => ['type' => 'string'],
                        'value' => ['type' => 'string'],
                        'location' => ['type' => 'string', 'enum' => ['header', 'query']],
                    ],
                ],
                'timeout' => [
                    'type' => 'integer',
                    'description' => 'Request timeout in seconds',
                ],
                'response_key' => [
                    'type' => 'string',
                    'description' => 'Context key to store response data',
                    'default' => 'http_response',
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getOutputData(): array
    {
        return [
            'http_response' => 'The response body (or custom key)',
            'status_code' => 'The HTTP status code',
            'response_headers' => 'The response headers',
        ];
    }
}
