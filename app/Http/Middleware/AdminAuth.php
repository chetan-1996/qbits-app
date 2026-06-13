<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedPassword = config('app.admin_password');

        if (empty($expectedPassword)) {
            abort(403, 'Admin access not configured.');
        }

        $expectedHash = hash('sha256', $expectedPassword);

        // Check admin auth cookie (0 minutes = session cookie, expires on browser close)
        $cookieHash = $request->cookie('admin_auth');
        if ($cookieHash && hash_equals($expectedHash, $cookieHash)) {
            return $next($request);
        }

        if ($request->isMethod('post') && $request->has('admin_password')) {
            if (hash_equals($expectedPassword, $request->input('admin_password'))) {
                $response = redirect($request->url());
                $response->withCookie(cookie('admin_auth', $expectedHash, 0));
                return $response;
            }

            return redirect($request->url())
                ->with('error', 'Invalid password.')
                ->withInput();
        }

        return response(view('admin.login', [
            'actionUrl' => $request->url(),
        ]));
    }
}
