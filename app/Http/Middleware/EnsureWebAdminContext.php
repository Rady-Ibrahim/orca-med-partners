<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureWebAdminContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->session()->get('web_admin_id');
        $admin = $adminId ? Admin::query()->find($adminId) : null;

        if (! $admin || ! $admin->isActive()) {
            $request->session()->forget('web_admin_id');

            return redirect()->route('admin.login');
        }

        $request->setUserResolver(fn() => $admin);

        return $next($request);
    }
}
