@component('mail::message')
# ⚠️ Litige escaladé à l'administration

Le litige **#{{ $dispute->id }}** concernant la commande **{{ $dispute->order->order_number }}** a été escaladé à l'administration.

**Client :** {{ $dispute->user->firstname }} {{ $dispute->user->lastname }}  
**Vendeur :** {{ $dispute->seller->firstname }} {{ $dispute->seller->lastname }}  
**Motif :** {{ $dispute->reason }}

> {{ $dispute->description }}

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:3000') . '/admin/disputes/' . $dispute->id, 'color' => 'red'])
Traiter ce litige
@endcomponent

Merci,<br>
{{ config('app.name') }}
@endcomponent
