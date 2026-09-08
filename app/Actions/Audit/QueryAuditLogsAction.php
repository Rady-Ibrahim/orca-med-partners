<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class QueryAuditLogsAction
{
    public function execute(array $filters = [], bool $paginate = true): LengthAwarePaginator|AuditLog
    {
        $query = AuditLog::query()->latest('id');
        $query->when($filters['actor'] ?? null, fn(Builder $q, $actor) => $q->where(function (Builder $nested) use ($actor): void {
            $nested->where('actor_type', 'like', '%' . $actor . '%')->orWhere('actor_id', $actor);
        }));
        $query->when($filters['action'] ?? null, fn(Builder $q, $action) => $q->where('action', $action));
        $query->when($filters['entity'] ?? null, fn(Builder $q, $entity) => $q->where('auditable_type', $entity));
        $query->when($filters['entity_id'] ?? null, fn(Builder $q, $id) => $q->where('auditable_id', $id));
        $query->when($filters['date_from'] ?? null, fn(Builder $q, $date) => $q->whereDate('created_at', '>=', $date));
        $query->when($filters['date_to'] ?? null, fn(Builder $q, $date) => $q->whereDate('created_at', '<=', $date));

        return $paginate ? $query->paginate(20)->withQueryString() : $query->firstOrFail();
    }

    public function find(int $id): AuditLog
    {
        return AuditLog::query()->findOrFail($id);
    }
}
