<?php

declare(strict_types=1);

namespace App\Enums;

enum FinancialStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case SUPERSEDED = 'superseded';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
