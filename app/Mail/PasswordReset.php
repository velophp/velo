<?php

namespace App\Mail;

use App\Models\Collection;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public $token;

    public $collection;

    public $app_name;

    public $action_url;

    /**
     * Create a new message instance.
     */
    public function __construct($token, ?Collection $collection = null)
    {
        $this->token = $token;
        $this->collection = $collection;
        $this->app_name = config('app.name');

        // Replace with project frontend url or smth

        $this->action_url = url('/reset-password?token='.$token);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = 'Reset Password';

        if ($this->collection && isset($this->collection->options['mail_templates']['password_reset']['subject'])) {
            $subject = $this->collection->options['mail_templates']['password_reset']['subject'];
            if (empty($subject)) {
                $subject = 'Reset Password';
            }
        }

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $body = '';
        if ($this->collection && isset($this->collection->options['mail_templates']['password_reset']['body'])) {
            $body = $this->collection->options['mail_templates']['password_reset']['body'];
        }

        // Only supporting {{action_url}} and {{app_name}} for now
        $body = str_replace(
            ['{{action_url}}', '{{app_name}}'],
            [$this->action_url, $this->app_name],
            $body
        );

        return new Content(
            markdown: 'mail.password-reset',
            with: [
                'body' => $body,
                'action_url' => $this->action_url,
                'subject' => $this->envelope()->subject,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
