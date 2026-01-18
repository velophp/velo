<?php

namespace App\Mail;

use App\Models\Collection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Otp extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $otp,
        public Collection $collection,
        public string $appName
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->collection->options['mail_templates']['otp_email']['subject'] ?? 'Your OTP Code';
        
        return new Envelope(
            subject: $subject ?: 'Your OTP Code',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $body = '';
        if ($this->collection && isset($this->collection->options['mail_templates']['otp_email']['body'])) {
            $body = $this->collection->options['mail_templates']['otp_email']['body'];
        }

        $body = str_replace(
            ['{{otp}}', '{{app_name}}'],
            [$this->otp, $this->appName],
            $body
        );
        
        $body = preg_replace('/^[ \t]+/m', '', $body);

        return new Content(
            markdown: 'mail.otp',
            with: [
                'body' => $body,
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
