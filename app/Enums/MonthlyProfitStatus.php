<?php

declare(strict_types=1);

namespace App\Enums;

enum MonthlyProfitStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case SUPERSEDED = 'superseded';
}
