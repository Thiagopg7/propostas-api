<?php

namespace App\Enums;

enum ProposalAuditEvent: string
{
    case Created = 'CREATED';
    case UpdatedFields = 'UPDATED_FIELDS';
    case StatusChanged = 'STATUS_CHANGED';
    case DeletedLogical = 'DELETED_LOGICAL';
}
