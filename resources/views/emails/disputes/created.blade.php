@extends('emails.layout', [
    'title' => 'Nouveau litige reçu',
    'preheader' => 'Un client a ouvert un litige sur une commande.',
    'badge' => 'Litige',
    'accent' => '#b45309',
    'ctaLabel' => 'Voir le litige',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/seller/disputes/' . $dispute->id,
])

@section('content')
    <p style="margin:0 0 14px 0;">
        Un client a ouvert un litige sur la commande <strong>#{{ $dispute->order->order_number }}</strong>.
    </p>

    <div style="border:1px solid #f3d38b;background:#fff8e5;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0 0 8px 0;color:#6b4a00;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Motif</p>
        <p style="margin:0 0 10px 0;"><strong>{{ $dispute->reason }}</strong></p>
        <p style="margin:0;color:#42493e;">{{ $dispute->description }}</p>
    </div>

    <p style="margin:18px 0 0 0;">Répondez depuis votre espace vendeur afin de faciliter une résolution rapide.</p>
@endsection
