@extends('emails.layout', [
    'title' => 'Contenu signale',
    'preheader' => 'Un contenu necessite une verification de moderation.',
    'badge' => 'Moderation',
    'accent' => '#b91c1c',
    'ctaLabel' => 'Voir le contenu',
    'ctaUrl' => $url,
])

@section('content')
    <p style="margin:0 0 14px 0;">Bonjour <strong>{{ $name }}</strong>,</p>
    <p style="margin:0 0 18px 0;">Un contenu a ete signale par le systeme de moderation automatique.</p>

    <div style="border:1px solid #f3d1d1;background:#fff1f1;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0 0 8px 0;color:#7f1d1d;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Analyse</p>
        <p style="margin:0 0 6px 0;"><strong>Type :</strong> {{ $contentType }}</p>
        <p style="margin:0 0 6px 0;"><strong>ID :</strong> {{ $contentId }}</p>
        <p style="margin:0 0 6px 0;"><strong>Score de risque :</strong> {{ $score }}</p>
        <p style="margin:0 0 6px 0;"><strong>Raison :</strong> {{ $reason }}</p>
        <p style="margin:0 0 6px 0;"><strong>Categories :</strong> {{ $categories }}</p>
        <p style="margin:0;"><strong>Action :</strong> {{ $action }}</p>
    </div>

    <p style="margin:18px 0 0 0;">Veuillez examiner ce contenu dans les plus brefs delais.</p>
@endsection
