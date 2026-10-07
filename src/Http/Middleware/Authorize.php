<?php

namespace Atlas\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class Authorize
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(Gate::forUser($request->user())->allows('useAtlas'), 403, 'You are not allowed to use Atlas.');

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
