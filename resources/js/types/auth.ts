export type Role = 'super_admin' | 'admin' | 'staff';

export type User = {
    id: number;
    name: string;
    email: string;
    role: Role;
    role_label: string;
    must_change_password: boolean;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Abilities = {
    accessAdmin: boolean;
    viewFinancials: boolean;
    overridePrice: boolean;
};

export type Auth = {
    user: User;
    can: Abilities;
};

export type Flash = {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
