<?php

namespace App\Mail;

use App\Models\Container;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstanceCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'n8n Instance Created: ' . $this->container->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.instances.created',
            with: [
                'versions' => [
                    'latest' => 'Stable',
                    'next' => 'Beta',
                ],
            ],
        );
    }
}
