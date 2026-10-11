import { describe, expect, it } from 'vitest';
import { createPersonalMoneySettingsSchema } from './schemas';

describe('personal money activation validation', () => {
    const schema = createPersonalMoneySettingsSchema('2026-10-11');
    const values = { accountId: '1', startedAt: '2026-10-11', openingBalance: '-100.25' };
    it('accepts today and signed balances with two decimal places', () => {
        expect(schema.safeParse(values).success).toBe(true);
    });
    it('rejects future dates before submitting', () => {
        expect(schema.safeParse({ ...values, startedAt: '2026-10-12' }).success).toBe(false);
    });
    it.each(['1000000000000', '10.001', 'NaN', ''])(
        'rejects invalid opening balance %s',
        (openingBalance) => {
            expect(schema.safeParse({ ...values, openingBalance }).success).toBe(false);
        },
    );
});
