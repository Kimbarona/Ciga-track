<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesMasterData;

class ResellerPolicy
{
    use AuthorizesMasterData;
}
