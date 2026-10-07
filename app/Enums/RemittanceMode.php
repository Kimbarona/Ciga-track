<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RemittanceMode: string implements HasLabel
{
    case Cash = 'cash';
    case GCash = 'gcash';
    case Maya = 'maya';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::GCash => 'GCash',
            self::Maya => 'Maya',
            self::BankTransfer => 'Bank Transfer',
            self::Other => 'Other',
        };
    }

    /**
     * Map free-text values (e.g. from the old Excel tracker) to a mode.
     */
    public static function fromLoose(?string $value): self
    {
        $normalized = str((string) $value)->lower()->replaceMatches('/[^a-z]/', '')->toString();

        return match (true) {
            $normalized === '' , $normalized === 'cash' => self::Cash,
            str_contains($normalized, 'gcash') => self::GCash,
            str_contains($normalized, 'maya'), str_contains($normalized, 'paymaya') => self::Maya,
            str_contains($normalized, 'bank'), str_contains($normalized, 'transfer') => self::BankTransfer,
            default => self::Other,
        };
    }
}
