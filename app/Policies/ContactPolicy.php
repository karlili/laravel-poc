<?php

namespace App\Policies;

class ContactPolicy extends OwnedRecordPolicy
{
    protected function resource(): string
    {
        return 'contacts';
    }
}
