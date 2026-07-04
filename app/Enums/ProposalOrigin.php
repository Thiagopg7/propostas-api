<?php

namespace App\Enums;

enum ProposalOrigin: string
{
    case App = 'APP';
    case Site = 'SITE';
    case Api = 'API';
}
