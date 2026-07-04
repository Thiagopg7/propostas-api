<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Canceled = 'CANCELED';

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Canceled], true);
    }
}
