<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Actions;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Pstoute\LaravelWorkflows\Data\ActionResult;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

class WebhookAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'webhook';
    }

    public function getName(): string
    {
        return 'Send Webhook';
    }

    public function getDescription(): string
    {
        return 'Send an HTTP webhook request';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $url = $config['url'] ?? null;
        $method = strtoupper($config['method'] ?? 'POST');
        $payload = $config['payload'] ?? $config['data'] ?? [];
        $headers = $config['headers'] ?? [];
        $timeout = $config['timeout'] ?? config('workflows.actions.webhook.timeout', 30);
        $verifySSL = $config['verify_ssl'] ?? config('workflows.actions.webhook.verify_ssl', true);

        if (empty($url)) {
            return ActionResult::failure('No webhook URL specified');
        }

        try {
            $client = new Client([
                'timeout' => $timeout,
                'verify' => $verifySSL,
            ]);

            $options = [
                'headers' => array_merge([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'Laravel-Workflows/1.0',
                ], $headers),
            ];

            if (in_array($method, ['POST', 'PUT', 'PATCH']) && ! empty($payload)) {
                $options['json'] = $payload;
            } elseif (! empty($payload)) {
                $options['query'] = $payload;
            }

            $response = $client->request($method, $url, $options);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $responseData = json_decode($body, true) ?? $body;

            if ($statusCode >= 200 && $statusCode < 300) {
                return ActionResult::success('Webhook sent successfully', [
                    'status_code' => $statusCode,
                    'response' => $responseData,
                ]);
            }

            return ActionResult::failure("Webhook returned status {$statusCode}", metadata: [
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

        } catch (RequestException $e) {
            $response = $e->getResponse();
            $statusCode = $response?->getStatusCode();
            $body = $response?->getBody()->getContents();

            return ActionResult::failure(
                'Webhook request failed: ' . $e->getMessage(),
                $e,
                [
                    'status_code' => $statusCode,
                    'response' => $body,
                ]
            );
        } catch (\Throwable $e) {
            return ActionResult::failure('Webhook request failed: ' . $e->getMessage(), $e);
        }
    }

    public function getTimeout(): int
    {
        return config('workflows.actions.webhook.timeout', 30);
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
                    'description' => 'The webhook URL',
                    'required' => true,
                ],
                'method' => [
                    'type' => 'string',
                    'description' => 'HTTP method',
                    'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                    'default' => 'POST',
                ],
                'payload' => [
                    'type' => 'object',
                    'description' => 'Request payload',
                ],
                'headers' => [
                    'type' => 'object',
                    'description' => 'Additional HTTP headers',
                ],
                'timeout' => [
                    'type' => 'integer',
                    'description' => 'Request timeout in seconds',
                ],
                'verify_ssl' => [
                    'type' => 'boolean',
                    'description' => 'Verify SSL certificate',
                    'default' => true,
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
            'status_code' => 'The HTTP response status code',
            'response' => 'The response body',
        ];
    }
}
