@component('mail::message')
# 💬 Nouveau message dans votre litige

Un nouveau message a été posté dans le litige **#{{ $dispute->id }}**.

**Expéditeur :** {{ $message->user->firstname }} {{ $message->user->lastname }}

> {{ $message->message }}

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:3000') . '/disputes/' . $dispute->id])
Voir la conversation
@endcomponent

Cordialement,<br>
{{ config('app.name') }}
@endcomponent
