<?php

namespace App\Domain\Auth\Models\Mail;

use App\Domain\Collection\Models\Collection;
use App\Domain\Record\Models\Record;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginAlert extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $collection;

    public $record;

    public $app_name;

    public $date;

    public $device_name;

    public $ip_address;

    /**
     * Create a new message instance.
     */
    public function __construct(Collection $collection, Record $record, $device_name, $ip_address)
    {
        $this->collection = $collection;
        $this->record = $record;
        $this->device_name = $device_name ?? 'Unknown Device';
        $this->ip_address = $ip_address ?? 'Unknown IP';
        $this->app_name = config('app.name');
        $this->date = now()->format('Y-m-d H:i:s');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = 'Login Alert';

        if ($this->collection && isset($this->collection->options['mail_templates']['login_alert']['subject'])) {
            $subject = $this->collection->options['mail_templates']['login_alert']['subject'];
            if (empty($subject)) {
                $subject = 'Login Alert';
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
        if ($this->collection && isset($this->collection->options['mail_templates']['login_alert']['body'])) {
            $body = $this->collection->options['mail_templates']['login_alert']['body'];
        }

        $body = str_replace(
            ['{{app_name}}', '{{user_email}}', '{{date}}', '{{device_name}}', '{{ip_address}}'],
            [
                $this->app_name,
                $this->record->data->get('email'),
                $this->date,
                $this->device_name,
                $this->ip_address,
            ],
            $body
        );

        return new Content(
            markdown: 'mail.login-alert',
            with: [
                'body'    => $body,
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
