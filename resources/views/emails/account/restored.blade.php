{{-- "Your iClimate account has been restored" (App\Mail\AccountStatusMail). Markdown: keep lines unindented. --}}
<x-mail::message>
# {{ __('account_emails.restored.subject') }}

{{ __('account_emails.hello', ['name' => $name]) }}

{{ __('account_emails.restored.body', ['date' => $date]) }}

<x-mail::button :url="$url">
{{ __('account_emails.restored.button') }}
</x-mail::button>

{{ __('account_emails.restored.farms') }}

{{ __('account_emails.thanks') }}<br>
{{ __('account_emails.team') }}
</x-mail::message>
