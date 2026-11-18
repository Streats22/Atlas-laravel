<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access by route name. Reads are open to every role; writes need Manager
 * unless listed below.
 */
class EnforceRole
{
    /** Routes that need a minimum role regardless of HTTP method. */
    private const ALWAYS = [
        'users.*' => Role::Admin,
        'settings.*' => Role::Admin,
        'audiences.export' => Role::Manager,
        'audiences.contacts.export' => Role::Manager,
        'reports.export' => Role::Manager,
    ];

    /** Write routes Authors may use. */
    private const AUTHOR_WRITES = [
        'campaigns.store', 'campaigns.update', 'campaigns.duplicate', 'campaigns.test', 'campaigns.ab.update',
        'campaigns.variants.update', 'templates.store', 'builder.preview', 'media.store',
    ];

    /** Every signed-in user manages their own account. */
    private const SELF_SERVICE = ['profile.*', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $name = (string) $request->route()?->getName();

        if ($user !== null && ! self::allows($user->role, $name, $request->method())) {
            abort(403, 'Your role does not allow this.');
        }

        return $next($request);
    }

    public static function allows(Role $role, string $routeName, string $method = 'GET'): bool
    {
        return $role->atLeast(self::requiredFor($routeName, $method));
    }

    private static function requiredFor(string $name, string $method): Role
    {
        foreach (self::ALWAYS as $pattern => $role) {
            if (Str::is($pattern, $name)) {
                return $role;
            }
        }
        if (in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true) || Str::is(self::SELF_SERVICE, $name)) {
            return Role::Viewer;
        }
        foreach (self::AUTHOR_WRITES as $pattern) {
            if (Str::is($pattern, $name)) {
                return Role::Author;
            }
        }

        return Role::Manager;
    }
}
