@extends('emails.layout', [
    'title' => 'Nouveau message dans votre litige',
    'preheader' => 'Un nouveau message a été ajouté à la conversation du litige.',
    'badge' => 'Messagerie',
    'ctaLabel' => 'Voir la conversation',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/disputes/' . $dispute->id,
])

@section('content')
    <p style="margin:0 0 14px 0;">
        Un nouveau message a été posté dans le litige <strong>#{{ $dispute->id }}</strong>.
    </p>

    <p style="margin:0 0 8px 0;">
        <strong>Expéditeur :</strong> {{ $message->user->firstname }} {{ $message->user->lastname }}
    </p>

    <div style="border-left:4px solid #154212;background:#f9faf2;border-radius:12px;padding:14px 16px;margin:18px 0;">
        <p style="margin:0;color:#42493e;">{{ $message->message }}</p>
    </div>
@endsection
