import { describe, expect, it } from 'vitest';
import { createEventParser } from './chatRepository';
import { routeChatContext } from '../composables/context';

describe('assistant transport', () => {
    it('parses fragmented CRLF, multiple events, Unicode and heartbeat frames', () => {
        const events: unknown[] = [];
        const parse = createEventParser((event) => events.push(event));
        parse(': heartbeat\r\n\r\n');
        parse('id: 1\r\ndata: {"sequence":1,"type":"message.completed","payload":"Mé');
        parse('xico"}\r');
        parse('\n\r\ndata: {"sequence":2,"type":"run.completed","payload":{}}\n\n');
        expect(events).toEqual([
            { sequence: 1, type: 'message.completed', payload: 'México' },
            { sequence: 2, type: 'run.completed', payload: {} },
        ]);
    });
    it('uses account route parameters only on matching account routes', () => {
        expect(routeChatContext('admin.accounts', undefined).scope).toBe('global');
        expect(routeChatContext('admin.accounts.ledger', '42')).toEqual({
            scope: 'account',
            account_id: '42',
            route_name: 'admin.accounts.ledger',
        });
        expect(routeChatContext('admin.dashboard', '42').scope).toBe('global');
        expect(routeChatContext('admin.accounts.users', ['42']).scope).toBe('global');
    });
});
