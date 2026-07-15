@extends('emails.layout', [
    'title' => 'Commande finalisée',
    'preheader' => 'Votre commande ' . $order->order_number . ' est terminée.',
    'badge' => 'Merci',
    'ctaLabel' => 'Voir mes commandes',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/orders',
])

@section('content')
    <p style="margin:0 0 14px 0;">Bonjour <strong>{{ $order->user->firstname ?? 'Client' }}</strong>,</p>
    <p style="margin:0 0 18px 0;">
        Votre commande <strong>#{{ $order->order_number }}</strong> est désormais finalisée.
    </p>

    <div style="border:1px solid #c2c9bb;background:#f9faf2;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0 0 10px 0;color:#154212;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Récapitulatif</p>
        @foreach($order->items as $item)
            <div style="padding:9px 0;border-top:{{ $loop->first ? '0' : '1px solid #e2e3dc' }};">
                <strong>{{ $item->quantity }} x {{ $item->product->name ?? 'Produit' }}</strong>
                <span style="float:right;color:#154212;font-weight:800;">{{ number_format($item->unit_price * $item->quantity, 0, ',', ' ') }} FCFA</span>
            </div>
        @endforeach
    </div>

    <p style="margin:18px 0 0 0;">Nous espérons que votre achat répond à vos attentes. Merci d'avoir choisi AgriPulse.</p>
@endsection
