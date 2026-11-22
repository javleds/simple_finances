export type PersonalMoneySettings = {
    account_id: number;
    started_at: string;
    opening_balance: string;
    enabled: boolean;
    activated_at?: string | null;
    start_mode?: 'now' | 'date';
    transaction_cutoff_id?: number | null;
};
export type PersonalMoneyResolution = 'personal' | 'external' | 'pending';
export type PersonalMoneyEntry = {
    source_type: 'transaction' | 'reimbursement';
    source_id: number;
    account_name: string;
    concept: string;
    occurred_at: string;
    amount: string;
    kind:
        | 'personal_income'
        | 'personal_expense'
        | 'contribution'
        | 'out_of_pocket'
        | 'reimbursement';
    resolution: PersonalMoneyResolution | 'automatic';
    effect: string;
    raw_effect?: string;
};
export type PersonalMoneyState = {
    settings: PersonalMoneySettings | null;
    accounts: Array<{ id: number; name: string }>;
    account_available?: boolean;
    available_balance: string | null;
    pending_count: number;
    history: PersonalMoneyEntry[];
    meta: { current_page: number; last_page: number; total: number };
};
export type PersonalMoneyLink = Pick<PersonalMoneyEntry, 'source_type' | 'source_id'> & {
    resolution: PersonalMoneyResolution;
};

export type PersonalMoneySettingsPayload = {
    account_id: number;
    opening_balance: string;
    enabled: boolean;
    started_at?: string;
    start_mode?: 'now' | 'date';
    operation_key?: string;
};
