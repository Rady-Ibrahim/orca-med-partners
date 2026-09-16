<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Admin\AuthenticateAdminAction;
use App\Services\SecurityAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AdminWebAuthController extends Controller
{
    public function __construct(protected SecurityAuditService $securityAuditService) {}

    public function login(): mixed
    {
        return view('admin.login');
    }

    public function authenticate(Request $request, AuthenticateAdminAction $action): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $admin = $action->execute($credentials['username'], $credentials['password']);

        if (! $admin) {
            $this->securityAuditService->log('admin_login_failure', null, 'admin', null, [
                'context' => 'web_login',
                'username' => $credentials['username'] ?? null,
            ]);

            return back()->withErrors(['username' => 'بيانات الدخول غير صحيحة أو الحساب غير نشط.'])->withInput();
        }

        $this->securityAuditService->log('admin_login_success', $admin, 'admin', $admin->id, [
            'context' => 'web_login',
        ]);

        $request->session()->regenerate();
        $request->session()->put('web_admin_id', $admin->id);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('web_admin_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
