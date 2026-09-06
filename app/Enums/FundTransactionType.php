<?php

declare(strict_types=1);

namespace App\Enums;

enum FundTransactionType: string
{
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';
    case ADJUSTMENT = 'adjustment';
}
