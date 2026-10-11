export type PersonalMoneySettings = {
    account_id: number;
    started_at: string;
    opening_balance: number;
    enabled: boolean;
};
export type PersonalMoneyResolution = 'personal' | 'external' | 'pending';
export type PersonalMoneyEntry = {
    source_type: 'transaction' | 'reimbursement';
    source_id: number;
    account_name: string;
    concept: string;
    occurred_at: string;
    amount: number;
    kind:
        | 'personal_income'
        | 'personal_expense'
        | 'contribution'
        | 'out_of_pocket'
        | 'reimbursement';
    resolution: PersonalMoneyResolution | 'automatic';
    effect: number;
    raw_effect?: number;
};
export type PersonalMoneyState = {
    settings: PersonalMoneySettings | null;
    accounts: Array<{ id: number; name: string }>;
    account_available?: boolean;
    available_balance: number | null;
    pending_count: number;
    history: PersonalMoneyEntry[];
    meta: { current_page: number; last_page: number; total: number };
};
export type PersonalMoneyLink = Pick<PersonalMoneyEntry, 'source_type' | 'source_id'> & {
    resolution: PersonalMoneyResolution;
};
