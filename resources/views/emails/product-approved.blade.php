@extends('emails.layout', [
    'title' => 'Produit approuvé',
    'preheader' => 'Votre produit est maintenant visible sur AgriPulse.',
    'badge' => 'Catalogue',
    'ctaLabel' => 'Voir le catalogue',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/marketplace',
])

@section('content')
    <p style="margin:0 0 14px 0;">Bonjour <strong>{{ $user }}</strong>,</p>
    <p style="margin:0 0 18px 0;">
        Votre produit <strong>{{ $product }}</strong> a été approuvé par l'équipe AgriPulse.
    </p>

    <div style="border-left:4px solid #154212;background:#f9faf2;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0;color:#191c18;font-weight:700;">Il est désormais visible dans la marketplace et peut être commandé par les acheteurs.</p>
    </div>

    <p style="margin:18px 0 0 0;">Merci de contribuer à un catalogue fiable et utile pour la communauté agricole.</p>
@endsection
