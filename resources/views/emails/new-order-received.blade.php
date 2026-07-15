@extends('emails.layout', [
    'title' => 'Nouvelle commande reçue',
    'preheader' => 'Une nouvelle commande attend votre préparation dans AgriPulse.',
    'badge' => 'Espace vendeur',
    'ctaLabel' => 'Préparer la commande',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/admin/sellers',
])

@section('content')
    <p style="margin:0 0 14px 0;">Bonjour <strong>{{ $order->shop->user->firstname ?? 'Vendeur' }}</strong>,</p>
    <p style="margin:0 0 18px 0;">
        Vous avez reçu la commande <strong>#{{ $order->order_number }}</strong> d'un montant de
        <strong style="color:#154212;">{{ number_format($order->total_amount, 0, ',', ' ') }} FCFA</strong>.
    </p>

    <div style="border:1px solid #c2c9bb;background:#f9faf2;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0 0 10px 0;color:#154212;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Détail de la commande</p>
        @foreach($order->items as $item)
            <div style="padding:9px 0;border-top:{{ $loop->first ? '0' : '1px solid #e2e3dc' }};">
                <strong>{{ $item->quantity }} x {{ $item->product->name ?? 'Produit' }}</strong>
                <span style="float:right;color:#154212;font-weight:800;">{{ number_format($item->unit_price * $item->quantity, 0, ',', ' ') }} FCFA</span>
            </div>
        @endforeach
    </div>

    <p style="margin:0;"><strong>Adresse de livraison :</strong> {{ $order->shipping_address }}</p>
    <p style="margin:14px 0 0 0;">Connectez-vous à votre espace vendeur pour préparer la commande et suivre la livraison.</p>
@endsection
