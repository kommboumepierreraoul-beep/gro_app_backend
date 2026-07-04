<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    // -------------------------------------------------------------------------
    // REGISTER
    // -------------------------------------------------------------------------

    /**
     * Register a new regular user and send email verification.
     */
    public function registerUser(Request $request): JsonResponse
    {
        return $this->register($request, 'user');
    }

    /**
     * Register a new admin and send email verification.
     */
    public function registerAdmin(Request $request): JsonResponse
    {
        return $this->register($request, 'admin');
    }

    /**
     * Shared registration logic.
     */
    private function register(Request $request, string $role): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'lastname'  => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => ['required', 'confirmed', PasswordRule::defaults()],
            'phone'     => 'nullable|string|max:20',
            'gender'    => 'nullable|string|in:male,female',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $user = User::create([
                'firstname'         => $request->firstname,
                'lastname'          => $request->lastname,
                'email'             => $request->email,
                'password'          => Hash::make($request->password),
                'phone'             => $request->phone,
                'role'              => $role,
                'gender'            => $request->gender,
                'email_verified_at' => null,
            ]);

            // Créer le wallet automatiquement
            $user->wallet()->create([
                'balance'       => 0,
                'total_credited' => 0,
                'total_debited' => 0,
                'currency'      => 'XAF',
            ]);

            $this->sendVerificationEmail($user);

            ActivityLog::log(
                'user_joined',
                "{$user->firstname} {$user->lastname} a rejoint la plateforme",
                'User',
                $user->id
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please check your email to verify your account.',
                'user'    => $user,
                'token'   => $token,
            ], 201);
        } catch (\Exception $e) {
            return $this->serverError('Registration failed', $e);
        }
    }

    // -------------------------------------------------------------------------
    // EMAIL VERIFICATION
    // -------------------------------------------------------------------------

    /**
     * Send (or resend) a 6-digit OTP verification code to the user's email.
     */
    public function sendVerificationCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified.',
            ], 400);
        }

        $this->sendVerificationEmail($user);

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to ' . $user->email,
        ]);
    }

    /**
     * Verify email with the OTP code sent by email.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $user     = $request->user();
        $cacheKey = $this->otpCacheKey($user->id);
        $cached   = Cache::get($cacheKey);

        if (!$cached || $cached['code'] !== $request->code) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        $user->markEmailAsVerified();
        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
        ]);
    }

    // -------------------------------------------------------------------------
    // LOGIN
    // -------------------------------------------------------------------------

    /**
     * Login and return a Sanctum token.
     * Optionally enforce email verification.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        // Uncomment to require email verification before login:
        // if (!$user->hasVerifiedEmail()) {
        //     Auth::logout();
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Please verify your email before logging in.',
        //     ], 403);
        // }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    // -------------------------------------------------------------------------
    // PROFILE
    // -------------------------------------------------------------------------

    /**
     * Return the authenticated user's profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');
        return response()->json([
            'success' => true,
            'user'    => $user,
        ]);
    }

    /**
     * Get authenticated user data for OAuth flow.
     */
    public function getOAuthUser(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur vient de l'OAuth
            if (!$user->google_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'user' => $user->load(['wallet', 'profile'])
            ]);
        } catch (\Exception $e) {
            return $this->serverError('Erreur lors de la récupération du profil', $e);
        }
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'firstname' => 'sometimes|string|max:255',
            'lastname'  => 'sometimes|string|max:255',
            'phone'     => 'sometimes|nullable|string|max:20',
            'avatar'    => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $user->update($request->only('firstname', 'lastname', 'phone'));

            if ($request->hasFile('avatar')) {
                $profile = $user->profile ?? $user->profile()->create([]);
                if ($profile->avatar) {
                    Storage::disk('public')->delete($profile->avatar);
                }
                $path = $request->file('avatar')->store('avatars', 'public');
                $profile->update(['avatar' => $path]);
            }

            $user->load('profile');

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'user'    => $user,
            ]);
        } catch (\Exception $e) {
            return $this->serverError('Update failed', $e);
        }
    }

    // -------------------------------------------------------------------------
    // PASSWORD
    // -------------------------------------------------------------------------

    /**
     * Change password (requires current password).
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 401);
        }

        try {
            $user->update(['password' => Hash::make($request->password)]);

            // Revoke all tokens so the user must log in again on other devices
            $user->tokens()->delete();

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully.',
                'token'   => $token,
            ]);
        } catch (\Exception $e) {
            return $this->serverError('Password change failed', $e);
        }
    }

    /**
     * Send a password-reset link to the given email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $user     = User::where('email', $request->email)->first();
        $cacheKey = 'password_reset_' . $user->id;

        // Throttle: allow one request per 2 minutes
        if (Cache::has($cacheKey . '_throttle')) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting another reset code.',
            ], 429);
        }

        $code = $this->generateOtp();

        Cache::put($cacheKey, ['code' => $code], now()->addMinutes(15));
        Cache::put($cacheKey . '_throttle', true, now()->addMinutes(2));

        $user->notify(new ResetPasswordNotification($code));

        return response()->json([
            'success' => true,
            'message' => 'Password reset code sent to ' . $request->email,
        ]);
    }

    /**
     * Reset the password using the OTP code received by email.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email|exists:users,email',
            'code'     => 'required|digits:6',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $user     = User::where('email', $request->email)->first();
        $cacheKey = 'password_reset_' . $user->id;
        $cached   = Cache::get($cacheKey);

        if (!$cached || $cached['code'] !== $request->code) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset code.',
            ], 422);
        }

        try {
            $user->update(['password' => Hash::make($request->password)]);

            // Revoke all existing tokens
            $user->tokens()->delete();

            Cache::forget($cacheKey);
            Cache::forget($cacheKey . '_throttle');

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully. You can now log in.',
            ]);
        } catch (\Exception $e) {
            return $this->serverError('Password reset failed', $e);
        }
    }

    // -------------------------------------------------------------------------
    // LOGOUT & TOKEN
    // -------------------------------------------------------------------------

    /**
     * Logout (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
        ]);
    }

    /**
     * Logout from all devices (revoke all tokens).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices.',
        ]);
    }

    /**
     * Refresh the current Sanctum token.
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->user()->currentAccessToken()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully.',
            'token'   => $token,
        ]);
    }

    // -------------------------------------------------------------------------
    // GOOGLE OAUTH - VERSION AVEC CACHE (Solution au problème CSRF)
    // -------------------------------------------------------------------------

    /**
     * Redirect to Google OAuth.
     */
    public function redirectToGoogle()
    {
        try {
            // Générer un état CSRF
            $state = Str::random(40);

            // Stocker dans le cache au lieu de la session
            Cache::put('google_oauth_state_' . $state, true, now()->addMinutes(10));

            Log::info('Google OAuth initiated with cache', [
                'state' => $state,
                'cache_key' => 'google_oauth_state_' . $state
            ]);

            return Socialite::driver('google')
                ->stateless()
                ->with(['state' => $state])
                ->scopes(['email', 'profile'])
                ->redirect();
        } catch (\Exception $e) {
            Log::error('Google Redirect Error: ' . $e->getMessage());
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect("{$frontendUrl}/login?error=" . urlencode($e->getMessage()));
        }
    }

    /**
     * Handle Google OAuth callback.
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $state = $request->state;
            $cacheKey = 'google_oauth_state_' . $state;

            Log::info('Google Callback received', [
                'state_received' => $state,
                'cache_key' => $cacheKey,
                'cache_has' => Cache::has($cacheKey)
            ]);

            // 1. Validation CSRF avec cache
            if (!$request->has('state')) {
                Log::warning('Missing state parameter in callback');
                return $this->redirectWithError('Paramètre state manquant');
            }

            if (!Cache::has($cacheKey)) {
                Log::warning('Invalid or expired state', [
                    'state' => $state,
                    'cache_has' => Cache::has($cacheKey)
                ]);
                return $this->redirectWithError('Requête invalide - State expiré ou invalide');
            }

            // Supprimer le state du cache après utilisation
            Cache::forget($cacheKey);

            // 2. Récupération des données Google
            Log::info('Fetching Google user...');
            $googleUser = Socialite::driver('google')->stateless()->user();

            Log::info('Google user retrieved', [
                'email' => $googleUser->email,
                'google_id' => $googleUser->id,
                'name' => $googleUser->name
            ]);

            // 3. Validation des données
            if (!$googleUser->email) {
                Log::warning('No email provided by Google');
                return $this->redirectWithError('Email non fourni par Google');
            }

            if (!($googleUser->user['email_verified'] ?? false)) {
                Log::warning('Email not verified by Google', ['email' => $googleUser->email]);
                return $this->redirectWithError('Veuillez vérifier votre email avec Google');
            }

            // 4. Gestion utilisateur en transaction
            $user = DB::transaction(function () use ($googleUser) {
                // Recherche par google_id
                $user = User::where('google_id', $googleUser->id)->first();
                if ($user) {
                    Log::info('User found by google_id', ['user_id' => $user->id]);
                    return $user;
                }

                // Recherche par email
                $existingUser = User::where('email', $googleUser->email)->first();
                if ($existingUser) {
                    Log::info('User found by email', ['user_id' => $existingUser->id]);

                    // Vérifier si l'email est lié à un autre compte Google
                    if ($existingUser->google_id && $existingUser->google_id !== $googleUser->id) {
                        throw new \Exception('Cet email est déjà associé à un autre compte Google');
                    }

                    // Mettre à jour l'utilisateur existant
                    $existingUser->update([
                        'google_id' => $googleUser->id,
                        'avatar' => $googleUser->avatar ?? $existingUser->avatar,
                        'email_verified_at' => now(),
                    ]);

                    ActivityLog::log(
                        'google_link',
                        "{$existingUser->firstname} {$existingUser->lastname} a lié son compte Google",
                        'User',
                        $existingUser->id
                    );

                    return $existingUser->fresh();
                }

                // Création nouvel utilisateur
                Log::info('Creating new user from Google');

                $newUser = User::create([
                    'firstname' => $googleUser->user['given_name'] ?? $googleUser->user['name'] ?? 'Utilisateur',
                    'lastname'  => $googleUser->user['family_name'] ?? '',
                    'email'     => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'avatar'    => $googleUser->avatar ?? null,
                    'password'  => Hash::make(Str::random(32)),
                    'email_verified_at' => now(),
                    'role'      => 'user',
                    'gender'    => null,
                ]);

                // Création du wallet
                $newUser->wallet()->create([
                    'balance' => 0,
                    'total_credited' => 0,
                    'total_debited' => 0,
                    'currency' => 'XAF',
                ]);

                // Log d'activité
                ActivityLog::log(
                    'google_register',
                    "{$newUser->firstname} {$newUser->lastname} s'est inscrit via Google",
                    'User',
                    $newUser->id
                );

                return $newUser;
            });

            // 5. Création du token avec expiration
            $token = $user->createToken('auth-token', ['*'], now()->addDays(7))->plainTextToken;
            Log::info('Token created for user', ['user_id' => $user->id]);

            // 6. Log de connexion
            ActivityLog::log(
                'google_login',
                "{$user->firstname} {$user->lastname} s'est connecté via Google",
                'User',
                $user->id
            );

            // 7. Redirection vers le frontend
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $redirectUrl = "{$frontendUrl}/oauth-callback?token={$token}";

            Log::info('Redirecting to frontend', ['url' => $redirectUrl]);

            return redirect($redirectUrl);
        } catch (\Exception $e) {
            Log::error('Google OAuth Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->redirectWithError($e->getMessage() ?: 'Erreur lors de la connexion avec Google');
        }
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    /**
     * Generate a 6-digit OTP and store it in cache for 10 minutes.
     */
    private function sendVerificationEmail(User $user): void
    {
        $code     = $this->generateOtp();
        $cacheKey = $this->otpCacheKey($user->id);

        Cache::put($cacheKey, ['code' => $code], now()->addMinutes(10));

        $user->notify(new EmailVerificationNotification($code));
    }

    /**
     * Generate a 6-digit OTP.
     */
    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get OTP cache key for a user.
     */
    private function otpCacheKey(int $userId): string
    {
        return 'email_verification_' . $userId;
    }

    /**
     * Redirect to frontend with error message.
     */
    private function redirectWithError(string $message)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        return redirect("{$frontendUrl}/login?error=" . urlencode($message));
    }

    /**
     * Return validation error response.
     */
    private function validationError($errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors'  => $errors,
        ], 422);
    }

    /**
     * Return server error response.
     */
    private function serverError(string $message, \Exception $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error'   => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
        ], 500);
    }
}
