@extends('emails.layout', [
    'title' => 'Boutique approuvée',
    'preheader' => 'Votre boutique est maintenant active sur AgriPulse.',
    'badge' => 'Boutique validée',
    'ctaLabel' => 'Ouvrir ma boutique',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/my-shop',
])

@section('content')
    <p style="margin:0 0 14px 0;">Bonjour <strong>{{ $user->firstname ?? 'Utilisateur' }}</strong>,</p>
    <p style="margin:0 0 18px 0;">
        Votre boutique <strong>{{ $shop->name }}</strong> a été approuvée par l'équipe AgriPulse.
    </p>

    <div style="border-left:4px solid #154212;background:#f9faf2;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0;color:#191c18;font-weight:700;">
            Vous pouvez maintenant publier vos produits, recevoir des commandes et gérer vos ventes depuis l'espace vendeur.
        </p>
    </div>

    <p style="margin:18px 0 0 0;">Merci de contribuer à une marketplace agricole fiable et professionnelle.</p>
@endsection
