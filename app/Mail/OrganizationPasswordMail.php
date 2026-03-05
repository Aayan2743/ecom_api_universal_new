<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $organization;
    public $password;

    /**
     * Create a new message instance.
     */

    
   public function __construct($organization, $password)
    {
        $this->organization = $organization;
        $this->password = $password;
    }


    /**
     * Get the message envelope.
     */
  public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Organization Login Credentials'
        );
    }
    /**
     * Get the message content definition.
     */
     public function content(): Content
    {
        return new Content(
            view: 'emails.organization-password',
            with: [
                'organization' => $this->organization,
                'password' => $this->password,
            ]
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