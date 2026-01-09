<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstanceDeleted extends Mailable
{
    use Queueable, SerializesModels;

    public $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'n8n Instance Deleted: ' . $this->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.instances.deleted',
        );
    }
}
