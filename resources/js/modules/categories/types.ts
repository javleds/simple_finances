export type CategoryTransactionType = 'income' | 'outcome' | 'both';

export type Category = {
    id: string;
    name: string;
    transactionType: CategoryTransactionType;
    accountId: string | null;
    scope: 'personal' | 'shared';
    transactionsCount: number;
    canManage: boolean;
};

export type CategoryCatalog = {
    categories: Category[];
    scope: 'personal' | 'shared';
    accountId: string | null;
    accountName: string | null;
    canManage: boolean;
};

export type CategoryDeletePayload = {
    action?: 'uncategorize' | 'reassign';
    targetCategoryId?: string | null;
};
