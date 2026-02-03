<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Actions;

use GuzzleHttp\Client;
use Pstoute\LaravelWorkflows\Data\ActionResult;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

class SlackMessageAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'slack';
    }

    public function getName(): string
    {
        return 'Send Slack Message';
    }

    public function getDescription(): string
    {
        return 'Send a message to Slack';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $webhookUrl = $config['webhook_url'] ?? config('workflows.actions.slack.webhook_url');
        $message = $config['message'] ?? null;
        $channel = $config['channel'] ?? null;
        $username = $config['username'] ?? 'Workflow Bot';
        $iconEmoji = $config['icon_emoji'] ?? ':robot_face:';
        $blocks = $config['blocks'] ?? null;
        $attachments = $config['attachments'] ?? null;

        if (empty($webhookUrl)) {
            return ActionResult::failure('No Slack webhook URL configured');
        }

        if (empty($message) && empty($blocks)) {
            return ActionResult::failure('No message or blocks specified');
        }

        try {
            $payload = [
                'username' => $username,
                'icon_emoji' => $iconEmoji,
            ];

            if ($message) {
                $payload['text'] = $message;
            }

            if ($channel) {
                $payload['channel'] = $channel;
            }

            if ($blocks) {
                $payload['blocks'] = $blocks;
            }

            if ($attachments) {
                $payload['attachments'] = $attachments;
            }

            $client = new Client(['timeout' => 10]);

            $response = $client->post($webhookUrl, [
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                return ActionResult::success('Slack message sent successfully', [
                    'channel' => $channel,
                ]);
            }

            return ActionResult::failure("Slack returned status {$statusCode}");

        } catch (\Throwable $e) {
            return ActionResult::failure('Failed to send Slack message: ' . $e->getMessage(), $e);
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
                'webhook_url' => [
                    'type' => 'string',
                    'description' => 'Slack webhook URL (uses config default if not specified)',
                ],
                'message' => [
                    'type' => 'string',
                    'description' => 'The message text',
                ],
                'channel' => [
                    'type' => 'string',
                    'description' => 'Target channel (e.g., #general)',
                ],
                'username' => [
                    'type' => 'string',
                    'description' => 'Bot username',
                    'default' => 'Workflow Bot',
                ],
                'icon_emoji' => [
                    'type' => 'string',
                    'description' => 'Bot icon emoji',
                    'default' => ':robot_face:',
                ],
                'blocks' => [
                    'type' => 'array',
                    'description' => 'Slack Block Kit blocks',
                ],
                'attachments' => [
                    'type' => 'array',
                    'description' => 'Slack attachments',
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
            'channel' => 'The channel the message was sent to',
        ];
    }
}
