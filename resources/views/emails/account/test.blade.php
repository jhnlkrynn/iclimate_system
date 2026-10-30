{{-- php artisan mail:test {email}: checks that sending works, in the same layout as the account emails. --}}
<x-mail::message>
# {{ __('account_emails.test.subject') }}

{{ __('account_emails.test.body') }}

{{ $sentAt }}
</x-mail::message>
