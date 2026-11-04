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
                    Number.isFinite(Number(value)) &&
                    Math.abs(Number(value)) <= 999999999999.99 &&
                    /^-?\d+(\.\d{1,2})?$/.test(value),
                'Indica un importe válido.',
            ),
    });
}
