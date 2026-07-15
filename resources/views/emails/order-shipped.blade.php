@extends('emails.layout', [
    'title' => 'Votre commande est en livraison',
    'preheader' => 'La commande ' . $order->order_number . ' est en route.',
    'badge' => 'Livraison',
    'ctaLabel' => 'Suivre la commande',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/track/' . $order->order_number,
])

@section('content')
    <p style="margin:0 0 14px 0;">Bonjour <strong>{{ $order->user->firstname ?? 'Client' }}</strong>,</p>
    <p style="margin:0 0 18px 0;">
        Votre commande <strong>#{{ $order->order_number }}</strong> est en cours de livraison.
    </p>

    <div style="border-left:4px solid #154212;background:#f9faf2;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0;color:#72796e;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Adresse de livraison</p>
        <p style="margin:6px 0 0 0;color:#191c18;font-weight:700;">{{ $order->shipping_address }}</p>
    </div>

    <p style="margin:0 0 10px 0;color:#154212;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Produits</p>
    @foreach($order->items as $item)
        <p style="margin:0 0 6px 0;">{{ $item->quantity }} x <strong>{{ $item->product->name ?? 'Produit' }}</strong></p>
    @endforeach

    <p style="margin:18px 0 0 0;">Dès réception, pensez à confirmer la livraison depuis votre espace client.</p>
@endsection
