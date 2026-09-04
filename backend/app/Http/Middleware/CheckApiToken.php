<?php
// app/Http/Middleware/CheckApiToken.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?? $request->query('token');
        
        if (!$token || $token !== env('AGENT_API_TOKEN')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        return $next($request);
    }
}