import { moneySchema, parseMoney, compareMoney, absMoney } from '@/modules/shared/lib/money';
import { z } from 'zod';

export function createPersonalMoneySettingsSchema(today: string) {
    return z.object({
        accountId: z.string().min(1, 'Selecciona una cuenta individual.'),
        startedAt: z
            .string()
            .regex(/^\d{4}-\d{2}-\d{2}$/, 'Selecciona una fecha.')
            .refine((value) => value <= today, 'La fecha inicial no puede estar en el futuro.'),
        openingBalance: z
            .string()
            .min(1, 'Indica tu saldo libre.')
            .refine(
                (value) =>
                    parseMoney(value) !== null &&
                    compareMoney(absMoney(value), '999999999999.99') <= 0 &&
                    /^-?\d+(\.\d{1,2})?$/.test(value),
                'Indica un importe válido.',
            ),
    });
}

export const personalMoneyStateSchema = z.object({
    settings: z
        .object({
            account_id: z.number().int(),
            started_at: z.string(),
            opening_balance: moneySchema,
            enabled: z.boolean(),
            activated_at: z.string().nullable().optional(),
            start_mode: z.enum(['now', 'date']).optional(),
            transaction_cutoff_id: z.number().int().nullable().optional(),
        })
        .nullable(),
    accounts: z.array(z.object({ id: z.number().int(), name: z.string() })),
    account_available: z.boolean().optional(),
    available_balance: moneySchema.nullable(),
    pending_count: z.number().int(),
    history: z.array(
        z.object({
            source_type: z.enum(['transaction', 'reimbursement']),
            source_id: z.number().int(),
            account_name: z.string(),
            concept: z.string(),
            occurred_at: z.string(),
            amount: moneySchema,
            kind: z.enum([
                'personal_income',
                'personal_expense',
                'contribution',
                'out_of_pocket',
                'reimbursement',
            ]),
            resolution: z.enum(['personal', 'external', 'pending', 'automatic']),
            effect: moneySchema,
            raw_effect: moneySchema.optional(),
        }),
    ),
    meta: z.object({
        current_page: z.number().int(),
        last_page: z.number().int(),
        total: z.number().int(),
    }),
});
