<?php

namespace App\Policies;

class CompanyPolicy extends OwnedRecordPolicy
{
    protected function resource(): string
    {
        return 'companies';
    }
}
