{{-- "Your iClimate account was not approved" (App\Mail\AccountStatusMail). Markdown: keep lines unindented. --}}
<x-mail::message>
# {{ __('account_emails.rejected.subject') }}

{{ __('account_emails.hello', ['name' => $name]) }}

{{ __('account_emails.rejected.body', ['date' => $date]) }}

**{{ __('account_emails.rejected.reason') }}** {{ $reason }}

**{{ __('account_emails.rejected.next') }}**

@foreach (__('account_emails.rejected.items') as $item)
- {{ $item }}
@endforeach

<x-mail::button :url="$url">
{{ __('account_emails.rejected.button') }}
</x-mail::button>

{{ __('account_emails.thanks') }}<br>
{{ __('account_emails.team') }}
</x-mail::message>
