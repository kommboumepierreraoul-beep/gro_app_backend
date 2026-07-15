@extends('emails.layout', [
    'title' => 'Candidature non retenue',
    'preheader' => 'Votre candidature n’a pas été retenue pour cette mission.',
    'badge' => 'Missions',
    'accent' => '#b45309',
    'ctaLabel' => 'Découvrir d’autres missions',
    'ctaUrl' => $missionsUrl,
])

@section('content')
    <p style="margin:0 0 14px 0;">Bonjour <strong>{{ $name }}</strong>,</p>
    <p style="margin:0 0 18px 0;">
        Merci pour votre candidature à la mission <strong>{{ $mission->title }}</strong>.
        Elle n'a pas été retenue cette fois-ci.
    </p>

    @if($reason)
        <div style="border:1px solid #f3d38b;background:#fff8e5;border-radius:12px;padding:14px 16px;margin:18px 0;color:#6b4a00;">
            <p style="margin:0 0 8px 0;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Motif</p>
            <p style="margin:0;">{{ $reason }}</p>
        </div>
    @endif

    <div style="border-left:4px solid #154212;background:#f9faf2;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0 0 8px 0;color:#154212;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Pour vos prochaines candidatures</p>
        <p style="margin:0;">Complétez votre profil, valorisez vos compétences clés et adaptez votre message à chaque mission.</p>
    </div>
@endsection
