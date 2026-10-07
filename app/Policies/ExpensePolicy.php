<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTransactions;

class ExpensePolicy
{
    use AuthorizesTransactions;
}
