<?php

namespace App\Mail;

use App\Models\AccountEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * An email about the person's own account: approved, rejected (not approved), deleted or restored, in their saved
 * language (resources/views/emails/account/<type>.blade.php, lang/{en,fil}/account_emails.php). It only ever carries
 * what the person may read: never IT's internal note or the name of the staff member who acted.
 */
class AccountStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string|int|null>  $words  name, date, reason, other, url … for the view
     */
    public function __construct(
        public string $type,
        public array $words,
        public ?int $accountEmailId,
        string $language,
    ) {
        $this->locale($language);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: trans('account_emails.'.$this->type.'.subject', [], $this->locale));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.account.'.$this->type, with: $this->words);
    }

    /** A queued send that gave up: the Last email line says Failed. */
    public function failed(Throwable $e): void
    {
        if ($this->accountEmailId !== null) {
            AccountEmail::query()->whereKey($this->accountEmailId)->update(['status' => AccountEmail::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }
}
