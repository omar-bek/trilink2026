<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtAuthenticate
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['message' => 'Token not provided'], 401);
            }

            if ($this->authService->isTokenBlacklisted($token)) {
                return response()->json(['message' => 'Token has been revoked'], 401);
            }

            $user = JWTAuth::parseToken()->authenticate();
            if (!$user || !$user->isActive()) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            auth()->setUser($user);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        return $next($request);
    }
}
