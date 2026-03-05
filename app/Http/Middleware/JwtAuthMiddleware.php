<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;

class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {

            return response()->json([
                'success' => false,
                'error'   => 'TOKEN_EXPIRED',
                'message' => 'Your session has expired. Please login again.',
            ], 422);

        } catch (TokenInvalidException $e) {

            return response()->json([
                'success' => false,
                'error'   => 'TOKEN_INVALID',
                'message' => 'Invalid authentication token.',
            ], 422);

        } catch (TokenBlacklistedException $e) {

            return response()->json([
                'success' => false,
                'error'   => 'TOKEN_BLACKLISTED',
                'message' => 'Token has been revoked. Please login again.',
            ], 422);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'error'   => 'TOKEN_MISSING',
                'message' => 'Authorization token not provided.',
            ], 422);
        }

        return $next($request);
    }
}