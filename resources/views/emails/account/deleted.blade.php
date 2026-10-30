{{-- "Your iClimate account has been deleted" (App\Mail\AccountStatusMail): the same reason words as the login panel.
     Never IT's internal note or the name of who deleted it. Markdown: keep lines unindented. --}}
<x-mail::message>
# {{ __('account_emails.deleted.subject') }}

{{ __('account_emails.hello', ['name' => $name]) }}

{{ __('account_emails.deleted.body', ['date' => $date]) }}

**{{ __('account_emails.deleted.reason') }}** {{ $reason }}
@if (filled($other))

**{{ __('account_emails.deleted.details') }}** {{ $other }}
@endif

{{ __('account_emails.deleted.mistake', ['days' => $days]) }}

{{ __('account_emails.thanks') }}<br>
{{ __('account_emails.team') }}
</x-mail::message>
