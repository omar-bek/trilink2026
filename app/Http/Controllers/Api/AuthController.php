<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCompanyRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function registerCompany(RegisterCompanyRequest $request): JsonResponse
    {
        $result = $this->authService->registerCompany($request->validated());

        return response()->json([
            'message' => 'Company registered successfully',
            'data' => $result,
        ], 201)->withCookie($this->refreshCookie($result['refresh_token']));
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'message' => 'User registered successfully',
            'data' => $result,
        ], 201)->withCookie($this->refreshCookie($result['refresh_token']));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->email, $request->password);

        if (!$result) {
            return response()->json(['message' => 'Invalid credentials or account inactive'], 401);
        }

        return response()->json([
            'message' => 'Login successful',
            'data' => $result,
        ])->withCookie($this->refreshCookie($result['refresh_token']));
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token') ?? $request->input('refresh_token');

        if (!$refreshToken) {
            return response()->json(['message' => 'Refresh token required'], 400);
        }

        $result = $this->authService->refresh($refreshToken);

        if (!$result) {
            return response()->json(['message' => 'Invalid or expired refresh token'], 401);
        }

        return response()->json([
            'message' => 'Token refreshed',
            'data' => $result,
        ])->withCookie($this->refreshCookie($result['refresh_token']));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $token = $this->authService->forgotPassword($request->email);

        // Always return success to prevent email enumeration
        return response()->json([
            'message' => 'If the email exists, a reset link has been sent',
            'data' => app()->environment('local') ? ['token' => $token] : null,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|confirmed|min:8',
        ]);

        $success = $this->authService->resetPassword(
            $request->email,
            $request->token,
            $request->password
        );

        if (!$success) {
            return response()->json(['message' => 'Invalid or expired reset token'], 400);
        }

        return response()->json(['message' => 'Password reset successfully']);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $refreshToken = $request->cookie('refresh_token');

        if ($token) {
            $this->authService->logout($token, $refreshToken);
        }

        return response()->json(['message' => 'Logged out successfully'])
            ->withCookie(cookie()->forget('refresh_token'));
    }

    public function me(): JsonResponse
    {
        $user = auth()->user()->load('company');

        return response()->json(['data' => $user]);
    }

    private function refreshCookie(string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            'refresh_token',
            $token,
            config('jwt.refresh_ttl', 20160),
            '/',
            null,
            true,  // secure
            true,  // httpOnly
            false,
            'Strict'
        );
    }
}
