@php
    $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/');
    $logoUrl = $logoUrl ?? $frontendUrl . '/logo_agri_pulse.png';
    $emailTitle = $title ?? 'AgriPulse';
    $emailPreheader = $preheader ?? 'Notification AgriPulse';
    $badge = $badge ?? 'AgriPulse';
    $accent = $accent ?? '#154212';
    $ctaLabel = $ctaLabel ?? null;
    $ctaUrl = $ctaUrl ?? null;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $emailTitle }}</title>
</head>
<body style="margin:0;padding:0;background:#f9faf2;color:#191c18;font-family:Inter,Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ $emailPreheader }}
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#f9faf2;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;">
                    <tr>
                        <td style="padding:0 0 14px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="left" style="vertical-align:middle;">
                                        <img src="{{ $logoUrl }}" alt="AgriPulse" width="44" height="44" style="display:inline-block;width:44px;height:44px;border-radius:12px;vertical-align:middle;margin-right:10px;">
                                        <span style="display:inline-block;vertical-align:middle;font-size:20px;font-weight:800;color:#154212;letter-spacing:0;">AgriPulse</span>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="display:inline-block;border:1px solid #c2c9bb;background:#eaf3de;color:#154212;border-radius:999px;padding:7px 11px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;">
                                            {{ $badge }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#ffffff;border:1px solid rgba(194,201,187,.72);border-radius:16px;overflow:hidden;box-shadow:0 18px 46px rgba(21,66,18,.08);">
                            <div style="height:6px;background:{{ $accent }};"></div>
                            <div style="padding:28px 26px 8px 26px;">
                                <h1 style="margin:0;color:#191c18;font-size:24px;line-height:1.25;font-weight:850;letter-spacing:0;">
                                    {{ $emailTitle }}
                                </h1>
                            </div>

                            <div style="padding:10px 26px 26px 26px;color:#42493e;font-size:15px;line-height:1.7;">
                                @yield('content')

                                @if($ctaLabel && $ctaUrl)
                                    <div style="margin-top:24px;">
                                        <a href="{{ $ctaUrl }}" style="display:inline-block;background:#154212;color:#ffffff;text-decoration:none;border-radius:10px;padding:13px 18px;font-size:14px;font-weight:800;">
                                            {{ $ctaLabel }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 8px 0 8px;text-align:center;color:#72796e;font-size:12px;line-height:1.6;">
                            <p style="margin:0 0 6px 0;font-weight:700;color:#3b6934;">AgriPulse, la plateforme agricole connectée.</p>
                            <p style="margin:0;">Marketplace, missions, communauté et portefeuille sécurisé.</p>
                            <p style="margin:10px 0 0 0;">© {{ date('Y') }} AgriPulse. Tous droits réservés.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
