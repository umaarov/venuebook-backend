<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class CheckOwner
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->role !== 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Wedding hall owner privileges required.',
            ], ResponseAlias::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
