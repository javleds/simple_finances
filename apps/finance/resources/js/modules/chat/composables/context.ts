import type { ChatContext } from '../repositories/chatRepository';
export function routeChatContext(name: unknown, accountId: unknown): ChatContext {
    const routeName = typeof name === 'string' ? name : null;
    const account =
        routeName?.startsWith('admin.accounts.') &&
        typeof accountId === 'string' &&
        /^[1-9]\d*$/.test(accountId)
            ? accountId
            : null;
    return { scope: account ? 'account' : 'global', account_id: account, route_name: routeName };
}
