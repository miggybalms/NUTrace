<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogUserLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Check if user just authenticated during this request
        if (Auth::check() && !session()->has('login_logged')) {
            try {
                $user = Auth::user();
                DB::table('audit_logs')->insert([
                    'user_id' => $user->id,
                    'action_type' => 'LOGIN',
                    'notes' => 'User logged in from IP: ' . $request->ip(),
                    'action_description' => 'User ' . ($user->full_name ?? $user->email) . ' authenticated successfully from ' . $request->ip(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Mark that we've logged this login
                session()->put('login_logged', true);
            } catch (\Exception $e) {
                // Silently fail to avoid breaking authentication
            }
        }

        return $response;
    }
}
