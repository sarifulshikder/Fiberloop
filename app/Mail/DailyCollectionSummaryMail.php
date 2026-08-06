<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyCollectionSummaryMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public array $stats;

    public function __construct(array $stats)
    {
        $this->stats = $stats;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Daily Collection Summary — ' . $this->stats['date'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-collection-summary',
            with: ['stats' => $this->stats],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
