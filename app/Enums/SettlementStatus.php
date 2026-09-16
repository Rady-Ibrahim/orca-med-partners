<?php

declare(strict_types=1);

namespace App\Enums;

enum SettlementStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case SUPERSEDED = 'superseded';
}
