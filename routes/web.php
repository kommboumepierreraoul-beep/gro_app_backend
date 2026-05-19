<?php

// =======================================================================
// FICHIER : routes/web.php
// Ajoutez temporairement cette route pour tester votre config Gmail
// SUPPRIMEZ-LA après avoir confirmé que ça marche !
// =======================================================================

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\AuthController;


Route::get('/test-gmail', function () {

    $destinataire = 'raoulkm2006@gmail'; // ← Changez par votre adresse de réception

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


Route::get('/test-brevo', function () {
    \Illuminate\Support\Facades\Mail::raw(
        '✅ Test Brevo SMTP OK depuis Laravel !',
        fn($m) => $m->to('raoulkm2006@gmail.com')->subject('Test Brevo')
    );
    return 'Email envoyé !';
});

Route::get('/auth/google/callback', [AuthController::class, 'callback']);
Route::get('/test-ssl', function () {
    return file_get_contents('https://www.google.com');
});