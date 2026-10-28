<?php

namespace App\Mail;

use App\Mail\Concerns\LocalizesToRecipient;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Email address confirmation. Sent synchronously (not queued) so the link is
 * in the inbox while the visitor is still looking at the "check your mail"
 * page. The signed URL grants verification, so the body is kept out of the
 * mail log the same way the password-reset link is.
 */
class EmailVerificationMail extends Mailable
{
    use SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public string $verifyUrl,
        public string $email,
    ) {
        $this->localizeTo($this->email);
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [PasswordResetMail::SENSITIVE_HEADER => '1'],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('client.auth.mail_verify_subject', ['company' => company_name()]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email',
            with: [
                'verifyUrl' => $this->verifyUrl,
                'email' => $this->email,
                'companyName' => company_name(),
            ],
        );
    }
}
