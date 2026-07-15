@extends('emails.layout', [
    'title' => 'Litige résolu',
    'preheader' => 'Une décision a été enregistrée pour votre litige.',
    'badge' => 'Résolution',
    'ctaLabel' => 'Consulter le litige',
    'ctaUrl' => rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/') . '/disputes/' . $dispute->id,
])

@section('content')
    <p style="margin:0 0 14px 0;">
        Votre litige sur la commande <strong>#{{ $dispute->order->order_number }}</strong> a été résolu.
    </p>

    @if($dispute->admin_notes)
        <div style="border-left:4px solid #154212;background:#f9faf2;border-radius:12px;padding:14px 16px;margin:18px 0;">
            <p style="margin:0 0 8px 0;color:#154212;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Décision</p>
            <p style="margin:0;">{{ $dispute->admin_notes }}</p>
        </div>
    @endif

    @if($dispute->refund_amount)
        <div style="background:#eaf3de;border:1px solid #c2c9bb;border-radius:12px;padding:16px;margin:18px 0;">
            <p style="margin:0;color:#72796e;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">Remboursement</p>
            <p style="margin:5px 0 0 0;color:#154212;font-size:24px;font-weight:900;">
                {{ number_format($dispute->refund_amount, 0, ',', ' ') }} FCFA
            </p>
        </div>
    @endif
@endsection
