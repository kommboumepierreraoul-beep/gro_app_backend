@extends('emails.layout', [
    'title' => 'Litige escaladé',
    'preheader' => 'Un litige nécessite une intervention administrateur.',
    'badge' => 'Administration',
    'accent' => '#b91c1c',
    'ctaLabel' => 'Traiter le litige',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/admin/disputes/' . $dispute->id,
])

@section('content')
    <p style="margin:0 0 14px 0;">
        Le litige <strong>#{{ $dispute->id }}</strong> concernant la commande
        <strong>#{{ $dispute->order->order_number }}</strong> a été escaladé à l'administration.
    </p>

    <div style="border:1px solid #f3d1d1;background:#fff1f1;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0 0 8px 0;color:#7f1d1d;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Informations</p>
        <p style="margin:0 0 6px 0;"><strong>Client :</strong> {{ $dispute->user->firstname }} {{ $dispute->user->lastname }}</p>
        <p style="margin:0 0 6px 0;"><strong>Vendeur :</strong> {{ $dispute->seller->firstname }} {{ $dispute->seller->lastname }}</p>
        <p style="margin:0;"><strong>Motif :</strong> {{ $dispute->reason }}</p>
    </div>

    <div style="border-left:4px solid #b91c1c;background:#f9faf2;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0;">{{ $dispute->description }}</p>
    </div>
@endsection
