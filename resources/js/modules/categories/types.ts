export type Category = {
    id: string;
    name: string;
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
