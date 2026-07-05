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

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Canceled],
            self::Submitted => [self::Approved, self::Rejected, self::Canceled],
            self::Approved, self::Rejected, self::Canceled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
