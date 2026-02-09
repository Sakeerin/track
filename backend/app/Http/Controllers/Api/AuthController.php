<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Login with email and password
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            AuditLog::log(
                AuditLog::ACTION_LOGIN_FAILED,
                null,
                User::class,
                null,
                null,
                null,
                ['email' => $request->email]
            );

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->canLogin()) {
            return response()->json([
                'success' => false,
                'error' => 'Account is disabled',
                'error_code' => 'ACCOUNT_DISABLED',
            ], 403);
        }

        // Revoke old tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth-token', ['*'], now()->addDays(7));

        // Record login
        $user->recordLogin($request->ip());

        AuditLog::log(
            AuditLog::ACTION_LOGIN,
            $user,
            User::class,
            $user->id
        );

        Log::info('User logged in', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at?->toISOString(),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Redirect to OAuth provider
     */
    public function redirectToProvider(string $provider): JsonResponse
    {
        if (!in_array($provider, ['google', 'microsoft'])) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid OAuth provider',
                'error_code' => 'INVALID_PROVIDER',
            ], 400);
        }

        try {
            $url = Socialite::driver($provider)
                ->stateless()
                ->redirect()
                ->getTargetUrl();

            return response()->json([
                'success' => true,
                'data' => ['redirect_url' => $url],
            ]);
        } catch (\Exception $e) {
            Log::error('OAuth redirect failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'OAuth configuration error',
                'error_code' => 'OAUTH_ERROR',
            ], 500);
        }
    }

    /**
     * Handle OAuth callback
     */
    public function handleProviderCallback(Request $request, string $provider): JsonResponse
    {
        if (!in_array($provider, ['google', 'microsoft'])) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid OAuth provider',
                'error_code' => 'INVALID_PROVIDER',
            ], 400);
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Check if user exists with this provider
            $user = User::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if (!$user) {
                // Check if user exists with same email
                $user = User::where('email', $socialUser->getEmail())->first();

                if ($user) {
                    // Link provider to existing account
                    $user->update([
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'avatar' => $socialUser->getAvatar(),
                    ]);
                } else {
                    // Create new user - requires admin approval for roles
                    $user = User::create([
                        'name' => $socialUser->getName(),
                        'email' => $socialUser->getEmail(),
                        'avatar' => $socialUser->getAvatar(),
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'email_verified_at' => now(),
                        'is_active' => false, // Require admin activation
                    ]);

                    Log::info('New OAuth user registered - requires activation', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'provider' => $provider,
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => 'Account pending approval',
                        'error_code' => 'PENDING_APPROVAL',
                        'message' => 'Your account has been created but requires administrator approval.',
                    ], 403);
                }
            }

            if (!$user->canLogin()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Account is disabled',
                    'error_code' => 'ACCOUNT_DISABLED',
                ], 403);
            }

            // Revoke old tokens
            $user->tokens()->delete();

            // Create new token
            $token = $user->createToken('oauth-token', ['*'], now()->addDays(7));

            // Record login
            $user->recordLogin($request->ip());

            AuditLog::log(
                AuditLog::ACTION_LOGIN,
                $user,
                User::class,
                $user->id,
                null,
                null,
                ['provider' => $provider]
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $this->formatUser($user),
                    'token' => $token->plainTextToken,
                    'expires_at' => $token->accessToken->expires_at?->toISOString(),
                ],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('OAuth callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'OAuth authentication failed',
                'error_code' => 'OAUTH_FAILED',
            ], 500);
        }
    }

    /**
     * Get current authenticated user
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
                'error_code' => 'UNAUTHENTICATED',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatUser($user),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Logout current user
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLog::log(
                AuditLog::ACTION_LOGOUT,
                $user,
                User::class,
                $user->id
            );

            // Revoke current token
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLog::log(
                AuditLog::ACTION_LOGOUT,
                $user,
                User::class,
                $user->id,
                null,
                null,
                ['all_devices' => true]
            );

            // Revoke all tokens
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Refresh authentication token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
                'error_code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // Delete current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $token = $user->createToken('auth-token', ['*'], now()->addDays(7));

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at?->toISOString(),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Format user data for response
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'provider' => $user->provider,
            'is_active' => $user->is_active,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'last_login_at' => $user->last_login_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
        ];
    }
}
