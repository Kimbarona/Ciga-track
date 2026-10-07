export type BalanceStatus = 'may_balance' | 'paid' | 'overpaid';
export type PriceSource = 'default' | 'special' | 'retail' | 'manual';
export type RemittanceStatus = 'pending' | 'confirmed' | 'rejected';

export type ResellerOption = {
    id: number;
    name: string;
    type: 'reseller' | 'retail';
    location: string | null;
};

export type ProductOption = {
    id: number;
    code: string;
    name: string;
    stock: number;
};

export type Option = { value: string; label: string };

export type Balance = {
    reams_taken: number;
    total_taken: number;
    confirmed_remitted: number;
    pending_remitted: number;
    balance: number;
    status: BalanceStatus;
    status_label: string;
};

export type PricePreview = {
    unit_price: number | null;
    source: PriceSource | null;
    source_label: string | null;
    available: number;
    error: string | null;
};
