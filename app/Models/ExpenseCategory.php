<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    public const DEFAULTS = [
        'Delivery', 'Gas/Fare', 'Packaging', 'Load/Internet', 'Rent/Storage', 'Food/Allowance', 'Other',
    ];

    protected $fillable = ['name'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
