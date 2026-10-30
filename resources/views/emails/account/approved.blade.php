{{-- "Your iClimate account is approved" (App\Mail\AccountStatusMail). Markdown: keep lines unindented. --}}
<x-mail::message>
# {{ __('account_emails.approved.subject') }}

{{ __('account_emails.hello', ['name' => $name]) }}

{{ __('account_emails.approved.body', ['date' => $date]) }}

<x-mail::button :url="$url">
{{ __('account_emails.approved.button') }}
</x-mail::button>

**{{ __('account_emails.approved.can_do') }}**

@foreach (__('account_emails.approved.items') as $item)
- {{ $item }}
@endforeach

{{ __('account_emails.thanks') }}<br>
{{ __('account_emails.team') }}
</x-mail::message>
