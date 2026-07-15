@extends('emails.layout', [
    'title' => 'Réponse à votre litige',
    'preheader' => 'Le vendeur a répondu à votre litige.',
    'badge' => 'Support',
    'ctaLabel' => 'Voir le litige',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/disputes/' . $dispute->id,
])

@section('content')
    <p style="margin:0 0 14px 0;">
        Le vendeur a répondu au litige lié à la commande <strong>#{{ $dispute->order->order_number }}</strong>.
    </p>

    <div style="border-left:4px solid #154212;background:#f9faf2;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0 0 8px 0;color:#154212;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Réponse du vendeur</p>
        <p style="margin:0;">{{ $dispute->seller_response }}</p>
    </div>

    <p style="margin:18px 0 0 0;">Vous pouvez continuer l'échange depuis votre espace litiges.</p>
@endsection
