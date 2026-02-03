<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Illuminate\Support\Facades\Mail;
use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class SendEmailAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'send_email';
    }

    public function getName(): string
    {
        return 'Send Email';
    }

    public function getDescription(): string
    {
        return 'Send an email message';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $to = $config['to'] ?? null;
        $subject = $config['subject'] ?? '';
        $body = $config['body'] ?? $config['message'] ?? '';
        $template = $config['template'] ?? null;
        $data = $config['data'] ?? [];
        $cc = $config['cc'] ?? null;
        $bcc = $config['bcc'] ?? null;
        $from = $config['from'] ?? config('workflow-conductor.actions.send_email.from') ?? config('mail.from.address');
        $fromName = $config['from_name'] ?? config('workflow-conductor.actions.send_email.from_name') ?? config('mail.from.name');

        if (empty($to)) {
            return ActionResult::failure('No recipient specified');
        }

        try {
            Mail::send([], [], function ($message) use ($to, $subject, $body, $template, $data, $cc, $bcc, $from, $fromName) {
                $message->to(is_array($to) ? $to : [$to])
                    ->subject($subject)
                    ->from($from, $fromName);

                if ($cc) {
                    $message->cc(is_array($cc) ? $cc : [$cc]);
                }

                if ($bcc) {
                    $message->bcc(is_array($bcc) ? $bcc : [$bcc]);
                }

                if ($template) {
                    $message->html(view($template, $data)->render());
                } else {
                    $message->html($body);
                }
            });

            return ActionResult::success('Email sent successfully', [
                'sent_to' => $to,
                'subject' => $subject,
            ]);
        } catch (\Throwable $e) {
            return ActionResult::failure('Failed to send email: ' . $e->getMessage(), $e);
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
                'to' => [
                    'type' => 'string',
                    'description' => 'Recipient email address(es)',
                    'required' => true,
                ],
                'subject' => [
                    'type' => 'string',
                    'description' => 'Email subject line',
                    'required' => true,
                ],
                'body' => [
                    'type' => 'string',
                    'description' => 'Email body (HTML)',
                ],
                'template' => [
                    'type' => 'string',
                    'description' => 'Blade template name (alternative to body)',
                ],
                'data' => [
                    'type' => 'object',
                    'description' => 'Data to pass to the template',
                ],
                'cc' => [
                    'type' => 'string',
                    'description' => 'CC recipient(s)',
                ],
                'bcc' => [
                    'type' => 'string',
                    'description' => 'BCC recipient(s)',
                ],
                'from' => [
                    'type' => 'string',
                    'description' => 'From email address',
                ],
                'from_name' => [
                    'type' => 'string',
                    'description' => 'From name',
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
            'sent_to' => 'The email address(es) the email was sent to',
            'subject' => 'The email subject',
        ];
    }
}
