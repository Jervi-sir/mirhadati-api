<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OptionalSanctumAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Activate Sanctum guard (parse token if present)
        Auth::shouldUse('sanctum');
        $user = Auth::guard('sanctum')->user();

        // If a valid user exists, bind it to the request
        if ($user) {
            $request->setUserResolver(fn() => $user);
        }

        return $next($request);
    }
}
