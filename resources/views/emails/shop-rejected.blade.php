@extends('emails.layout', [
    'title' => 'Boutique à corriger',
    'preheader' => 'Votre demande de boutique nécessite des corrections.',
    'badge' => 'Validation boutique',
    'accent' => '#b45309',
    'ctaLabel' => 'Corriger ma boutique',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/create-shop',
])

@section('content')
    <p style="margin:0 0 14px 0;">Bonjour <strong>{{ $user->firstname ?? 'Utilisateur' }}</strong>,</p>
    <p style="margin:0 0 18px 0;">
        Votre demande pour la boutique <strong>{{ $shop->name }}</strong> n'a pas encore été validée.
    </p>

    <div style="border:1px solid #f3d38b;background:#fff8e5;border-radius:12px;padding:14px 16px;margin:18px 0;color:#6b4a00;">
        <p style="margin:0 0 8px 0;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Motif</p>
        <p style="margin:0;">{{ $reason }}</p>
    </div>

    <p style="margin:18px 0 0 0;">Veuillez corriger les informations demandées avant de soumettre à nouveau votre boutique.</p>
@endsection
