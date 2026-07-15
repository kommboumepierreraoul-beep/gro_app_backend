@extends('emails.layout', [
    'title' => 'Candidature acceptée',
    'preheader' => 'Votre candidature a été acceptée sur AgriPulse.',
    'badge' => 'Missions',
    'ctaLabel' => 'Voir la mission',
    'ctaUrl' => $missionUrl,
])

@section('content')
    <p style="margin:0 0 14px 0;">Bonjour <strong>{{ $name }}</strong>,</p>
    <p style="margin:0 0 18px 0;">
        Votre candidature pour la mission <strong>{{ $mission->title }}</strong> a été acceptée.
    </p>

    <div style="border:1px solid #c2c9bb;background:#f9faf2;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0 0 8px 0;color:#154212;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Détails de la mission</p>
        <p style="margin:0 0 6px 0;"><strong>Date de début :</strong> {{ $startDate }}</p>
        <p style="margin:0 0 6px 0;"><strong>Lieu :</strong> {{ $location }}</p>
        <p style="margin:0;"><strong>Rémunération :</strong> {{ $mission->remuneration_label }}</p>
        @if($mission->remuneration_conditions)
            <p style="margin:6px 0 0 0;"><strong>Conditions :</strong> {{ $mission->remuneration_conditions }}</p>
        @endif
    </div>

    @if(count($contacts))
        <div style="border-left:4px solid #154212;background:#ffffff;border-radius:12px;padding:14px 16px;margin:18px 0;">
            <p style="margin:0 0 8px 0;color:#154212;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Contacts</p>
            @foreach($contacts as $contact)
                <p style="margin:0 0 6px 0;"><strong>{{ ucfirst($contact['type']) }} :</strong> {{ $contact['value'] }}</p>
            @endforeach
        </div>
    @endif

    <p style="margin:18px 0 0 0;">Bonne mission avec AgriPulse.</p>
@endsection
