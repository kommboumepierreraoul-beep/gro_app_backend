<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

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
            'avatar'    => 'nullable|url',
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
                'avatar'            => $request->avatar,
                'role'              => $role,
                'email_verified_at' => null, // Not verified yet
            ]);

            // Fire the Registered event → triggers SendEmailVerificationNotification
            // (or use our custom notification below)
            $this->sendVerificationEmail($user);

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

        $user      = $request->user();
        $cacheKey  = $this->otpCacheKey($user->id);
        $cached    = Cache::get($cacheKey);

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
            'user'    => $user,
        ]);
    }

    //

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
        return response()->json([
            'success' => true,
            'user'    => $request->user(),
        ]);
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
            'avatar'    => 'sometimes|nullable|url',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $user->update($request->only('firstname', 'lastname', 'phone', 'avatar'));

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

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function otpCacheKey(int $userId): string
    {
        return 'email_verification_' . $userId;
    }

    private function validationError($errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors'  => $errors,
        ], 422);
    }

    private function serverError(string $message, \Exception $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error'   => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
        ], 500);
    }
}
