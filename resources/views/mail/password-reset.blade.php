<x-mail::message>
# {{ $subject ?? 'Reset Password' }}

{!! $body !!}

<x-mail::button :url="$action_url">
Reset Password
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
