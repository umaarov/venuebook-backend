<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckOwner
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->role !== 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Wedding hall owner privileges required.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
