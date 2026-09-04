<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ManagerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user || ($user->role !== 'manager' && $user->role !== 'admin')) {
            return response()->json([
                'message' => 'Accès réservé aux managers',
                'success' => false
            ], 403);
        }

        return $next($request);
    }
}