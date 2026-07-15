@extends('emails.layout', [
    'title' => 'Produit à corriger',
    'preheader' => 'Votre produit nécessite des corrections avant publication.',
    'badge' => 'Validation',
    'accent' => '#b45309',
    'ctaLabel' => 'Corriger mon produit',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/my-shop',
])

@section('content')
    <p style="margin:0 0 14px 0;">Bonjour <strong>{{ $user }}</strong>,</p>
    <p style="margin:0 0 18px 0;">
        Votre produit <strong>{{ $product }}</strong> n'a pas encore été validé.
    </p>

    <div style="border:1px solid #f3d38b;background:#fff8e5;border-radius:12px;padding:14px 16px;margin:18px 0;color:#6b4a00;">
        <p style="margin:0 0 8px 0;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Motif</p>
        <p style="margin:0;">{{ $reason }}</p>
    </div>

    <p style="margin:18px 0 0 0;">Veuillez appliquer les corrections nécessaires, puis soumettre à nouveau votre produit.</p>
@endsection
