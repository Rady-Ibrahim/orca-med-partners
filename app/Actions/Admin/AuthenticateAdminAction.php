<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

final class AuthenticateAdminAction
{
    public function execute(string $username, string $password): ?Admin
    {
        $admin = Admin::query()->where('username', $username)->first();

        return $admin && $admin->isActive() && Hash::check($password, $admin->password)
            ? $admin
            : null;
    }
}
