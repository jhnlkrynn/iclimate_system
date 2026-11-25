<?php

namespace App\Mail;

use App\Models\Farm;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FarmVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Farm $farm,
        public readonly string $decision,
        public readonly ?string $reason = null,
        public readonly string $localeCode = 'en',
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->decision === Farm::VERIFICATION_APPROVED
            ? __('farmer.farm_verification_email.subject_approved', [], $this->localeCode)
            : __('farmer.farm_verification_email.subject_rejected', [], $this->localeCode);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.farm-verification');
    }
}
