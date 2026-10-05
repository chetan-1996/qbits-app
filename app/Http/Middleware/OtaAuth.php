<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OTA portal access (MO-42).
 *
 * Operators log in with OTA_OPERATOR_PASSWORD -> role "operator".
 * The existing ADMIN_PASSWORD -> role "admin" (can also use `force`
 * and the 9.9.9 backup image). Mirrors the AdminAuth cookie pattern.
 */
class OtaAuth
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_OPERATOR = 'operator';

    public function handle(Request $request, Closure $next): Response
    {
        $role = self::role($request);

        if ($role !== null) {
            $request->attributes->set('ota_role', $role);
            return $next($request);
        }

        if ($request->isMethod('post') && $request->has('ota_password')) {
            $role = self::roleForPassword((string) $request->input('ota_password'));

            if ($role !== null) {
                $response = redirect(self::loginTarget($request));
                $cookie = $role === self::ROLE_ADMIN
                    ? cookie('admin_auth', hash('sha256', config('app.admin_password')), 0)
                    : cookie('ota_auth', hash('sha256', config('ota.operator_password')), 0);
                return $response->withCookie($cookie);
            }

            return redirect($request->url())
                ->with('error', 'Invalid password.')
                ->withInput();
        }

        return response(view('ota.login', [
            'actionUrl' => $request->url(),
        ]));
    }

    /**
     * Resolve the caller's role from the auth cookies, or null.
     */
    public static function role(Request $request): ?string
    {
        $admin = config('app.admin_password');
        if ($admin && hash_equals(hash('sha256', $admin), (string) $request->cookie('admin_auth'))) {
            return self::ROLE_ADMIN;
        }

        $operator = config('ota.operator_password');
        if ($operator && hash_equals(hash('sha256', $operator), (string) $request->cookie('ota_auth'))) {
            return self::ROLE_OPERATOR;
        }

        return null;
    }

    /**
     * Where to send the browser after a successful login: the page that was
     * originally requested, falling back to the job list. Same-origin only.
     */
    private static function loginTarget(Request $request): string
    {
        $target = (string) $request->input('redirect', '');
        $host = parse_url($target, PHP_URL_HOST);
        $path = parse_url($target, PHP_URL_PATH) ?: '';

        if (($host !== null && !hash_equals($request->getHost(), $host))
            || !str_starts_with($path, '/')
            || $path === '/ota/login') {
            return route('ota.jobs.index');
        }

        return $path . (parse_url($target, PHP_URL_QUERY) ? '?' . parse_url($target, PHP_URL_QUERY) : '');
    }

    private static function roleForPassword(string $password): ?string
    {
        $admin = config('app.admin_password');
        if ($admin && hash_equals($admin, $password)) {
            return self::ROLE_ADMIN;
        }

        $operator = config('ota.operator_password');
        if ($operator && hash_equals($operator, $password)) {
            return self::ROLE_OPERATOR;
        }

        return null;
    }
}
