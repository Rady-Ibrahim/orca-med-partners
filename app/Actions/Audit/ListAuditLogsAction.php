<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Collection;

final class ListAuditLogsAction
{
    /** @return Collection<int, AuditLog> */
    public function execute(): Collection
    {
        return AuditLog::query()->latest('id')->limit(50)->get();
    }
}
