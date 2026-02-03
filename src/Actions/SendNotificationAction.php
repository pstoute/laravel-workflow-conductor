<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;
use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class SendNotificationAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'send_notification';
    }

    public function getName(): string
    {
        return 'Send Notification';
    }

    public function getDescription(): string
    {
        return 'Send a Laravel notification';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $notificationClass = $config['notification'] ?? null;
        $notifiablePath = $config['notifiable'] ?? 'model';
        $params = $config['params'] ?? [];
        $channels = $config['channels'] ?? null;

        if ($notificationClass === null) {
            return ActionResult::failure('No notification class specified');
        }

        if (! class_exists($notificationClass)) {
            return ActionResult::failure("Notification class '{$notificationClass}' not found");
        }

        $notifiable = $context->get($notifiablePath);

        if ($notifiable === null) {
            return ActionResult::failure("Notifiable not found at path '{$notifiablePath}'");
        }

        try {
            // Create notification instance
            $notification = app()->make($notificationClass, $params);

            // Override channels if specified
            if ($channels !== null && method_exists($notification, 'via')) {
                $notification->via = fn () => (array) $channels;
            }

            // Send notification
            if (is_array($notifiable)) {
                Notification::send($notifiable, $notification);
            } elseif (in_array(Notifiable::class, class_uses_recursive($notifiable))) {
                $notifiable->notify($notification);
            } else {
                Notification::send([$notifiable], $notification);
            }

            return ActionResult::success('Notification sent successfully', [
                'notification' => $notificationClass,
                'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : gettype($notifiable),
            ]);
        } catch (\Throwable $e) {
            return ActionResult::failure('Failed to send notification: ' . $e->getMessage(), $e);
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
                'notification' => [
                    'type' => 'string',
                    'description' => 'Fully qualified notification class name',
                    'required' => true,
                ],
                'notifiable' => [
                    'type' => 'string',
                    'description' => 'Context path to the notifiable (default: "model")',
                    'default' => 'model',
                ],
                'params' => [
                    'type' => 'object',
                    'description' => 'Parameters to pass to the notification constructor',
                ],
                'channels' => [
                    'type' => 'array',
                    'description' => 'Override notification channels',
                    'items' => ['type' => 'string'],
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
            'notification' => 'The notification class that was sent',
            'notifiable_type' => 'The type of the notifiable',
        ];
    }
}
