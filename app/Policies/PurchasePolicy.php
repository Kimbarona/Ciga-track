<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTransactions;

class PurchasePolicy
{
    use AuthorizesTransactions;
}
