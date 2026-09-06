<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Admin\GetDashboardDataAction;
use Illuminate\View\View;

final class AdminDashboardController extends Controller
{
    public function __invoke(GetDashboardDataAction $action): View
    {
        return view('admin.dashboard', ['dashboard' => $action->execute((int) now()->year)]);
    }
}
