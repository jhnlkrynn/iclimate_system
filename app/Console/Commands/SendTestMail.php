<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Lets IT check that the mail settings in .env work: sends one short email, right away (never queued), to the address
 * given, in the same layout as the account emails, and says which mailer and From address were used.
 */
class SendTestMail extends Command
{
    protected $signature = 'mail:test {email : Where to send the test email}';

    protected $description = 'Send a test email to check that the mail settings in .env work';

    public function handle(): int
    {
        $to = trim((string) $this->argument('email'));
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $this->line('Mailer: '.$mailer.($mailer === 'smtp' ? ' ('.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port').')' : ''));
        $this->line('From: '.config('mail.from.name').' <'.config('mail.from.address').'>');
        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn('MAIL_MAILER='.$mailer.': the email is not really sent ('.($mailer === 'log' ? 'it is written to the log' : 'it is kept in memory').').');
        }

        $mail = new class extends Mailable
        {
            public function envelope(): Envelope
            {
                return new Envelope(subject: __('account_emails.test.subject'));
            }

            public function content(): Content
            {
                return new Content(markdown: 'emails.account.test', with: ['sentAt' => now()->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y g:i A')]);
            }
        };

        try {
            Mail::to($to)->sendNow($mail);
        } catch (Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());
            $this->line('Check MAIL_HOST, MAIL_PORT, MAIL_SCHEME, MAIL_USERNAME and MAIL_PASSWORD (for Gmail, an App Password).');

            return self::FAILURE;
        }

        $this->info('Test email sent to '.$to.'.');

        return self::SUCCESS;
    }
}
