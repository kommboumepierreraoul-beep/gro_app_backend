@extends('emails.layout', [
    'title' => 'Fonds crédités',
    'preheader' => 'Le paiement de la commande ' . $order->order_number . ' a été crédité sur votre wallet.',
    'badge' => 'Wallet vendeur',
    'ctaLabel' => 'Voir mon portefeuille',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/wallet',
])

@section('content')
    <p style="margin:0 0 14px 0;">Bonjour <strong>{{ $order->shop->user->firstname ?? 'Vendeur' }}</strong>,</p>
    <p style="margin:0 0 18px 0;">
        La commande <strong>#{{ $order->order_number }}</strong> est finalisée. Les fonds ont été crédités sur votre wallet.
    </p>

    <div style="background:#eaf3de;border:1px solid #c2c9bb;border-radius:12px;padding:16px;margin:18px 0;">
        <p style="margin:0;color:#72796e;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Montant crédité</p>
        <p style="margin:5px 0 0 0;color:#154212;font-size:26px;line-height:1.2;font-weight:900;">
            {{ number_format($order->total_amount, 0, ',', ' ') }} FCFA
        </p>
    </div>

    <p style="margin:0 0 10px 0;color:#154212;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Détail de la vente</p>
    @foreach($order->items as $item)
        <p style="margin:0 0 6px 0;">{{ $item->quantity }} x <strong>{{ $item->product->name ?? 'Produit' }}</strong></p>
    @endforeach
@endsection
