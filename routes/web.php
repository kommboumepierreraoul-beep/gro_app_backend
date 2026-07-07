<?php

// =======================================================================
// FICHIER : routes/web.php
// Ajoutez temporairement cette route pour tester votre config Gmail
// SUPPRIMEZ-LA après avoir confirmé que ça marche !
// =======================================================================

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-gmail', function () {

    $destinataire = 'raoulkm2006@gmail.com'; // ← Changez par votre adresse de réception

    try {
        Mail::raw(
            "✅ Test réussi !\n\nVotre configuration Gmail SMTP fonctionne correctement avec Laravel.\n\nEnvoyé depuis : " . config('app.name'),
            function ($message) use ($destinataire) {
                $message
                    ->to($destinataire)
                    ->subject('✅ Test Gmail SMTP — ' . config('app.name'));
            }
        );

        return response()->json([
            'success' => true,
            'message' => "Email envoyé avec succès à {$destinataire} !",
            'config'  => [
                'host'       => config('mail.mailers.smtp.host'),
                'port'       => config('mail.mailers.smtp.port'),
                'username'   => config('mail.mailers.smtp.username'),
                'encryption' => config('mail.mailers.smtp.encryption'),
                'from'       => config('mail.from.address'),
            ],
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Échec de l\'envoi.',
            'error'   => $e->getMessage(),
            'conseil' => match (true) {
                str_contains($e->getMessage(), 'Username and Password') =>
                '❌ App Password incorrect. Vérifiez MAIL_PASSWORD dans .env',
                str_contains($e->getMessage(), 'Connection') =>
                '❌ Connexion impossible. Vérifiez MAIL_HOST et MAIL_PORT',
                str_contains($e->getMessage(), 'timeout') =>
                '❌ Timeout. Le port 587 est peut-être bloqué — essayez MAIL_PORT=465 + MAIL_ENCRYPTION=ssl',
                default =>
                '❌ Erreur inconnue. Consultez le message ci-dessus.',
            },
        ], 500);
    }

    
});


Route::get('/debug/mail', function () {
    return [
        'mailer' => config('mail.default'),
        'host' => config('mail.mailers.smtp.host'),
        'port' => config('mail.mailers.smtp.port'),
        'encryption' => config('mail.mailers.smtp.encryption'),
        'username' => config('mail.mailers.smtp.username'),
    ];
});



// Route::get('/test-brevo', function () {

//     try {

//         Mail::raw('✅ Félicitations ! Brevo fonctionne correctement avec Laravel.', function ($message) {

//             $message->to('kommboumepierreraoul@gmail.com') // Remplace par ton adresse
//                 ->subject('Test SMTP Brevo');
//         });

//         return response()->json([
//             'success' => true,
//             'message' => 'Email envoyé avec succès.'
//         ]);
//     } catch (\Exception $e) {

//         return response()->json([
//             'success' => false,
//             'message' => 'Échec de l\'envoi.',
//             'error' => $e->getMessage(),
//         ], 500);
//     }
// });

use App\Services\BrevoMailService;

Route::get('/test-brevo', function (BrevoMailService $brevo) {

    $brevo->send(
        'raoulkm2006@gmail.com', // Remplace par ton adresse
        'Raoul',
        'Test Brevo API',
        '
        <h2>AgriPulse</h2>
        <p>Si tu reçois cet email, l\'API Brevo fonctionne correctement ✅</p>
        <p>Envoyé le : ' . now() . '</p>
        '
    );

    return response()->json([
        'success' => true,
        'message' => 'Email envoyé avec succès.'
    ]);
});

Route::get('/auth/google/callback', [AuthController::class, 'callback']);

// Route::get('/test-ssl', function () {
//     return file_get_contents('https://www.google.com');
// });

// Route::get('/test-upload', function () {
//     return view('test-upload');
// });
