@extends('emails.layout', [
    'title' => $title,
    'preheader' => $preheader,
    'badge' => $badge ?? 'Sécurité',
    'accent' => $accent ?? '#154212',
])

@section('content')
    <p style="margin:0 0 14px 0;">Bonjour <strong>{{ $firstname }}</strong>,</p>
    <p style="margin:0 0 18px 0;">{{ $intro }}</p>

    <div style="background:#eaf3de;border:1px solid #c2c9bb;border-radius:14px;padding:20px;margin:22px 0;text-align:center;">
        <p style="margin:0 0 8px 0;color:#72796e;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;">Code de sécurité</p>
        <div style="color:#154212;font-size:34px;line-height:1.1;font-weight:900;letter-spacing:8px;font-family:Consolas,Menlo,monospace;">
            {{ $code }}
        </div>
    </div>

    <p style="margin:0 0 14px 0;">Ce code est valide pendant <strong>{{ $validity }}</strong>.</p>
    <p style="margin:0;color:#72796e;">{{ $ignoreText }}</p>
@endsection
