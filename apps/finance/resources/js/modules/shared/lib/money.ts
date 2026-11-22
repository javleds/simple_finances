import Big from 'big.js';
import { z } from 'zod';

const Decimal = Big();
Decimal.strict = true;
Decimal.DP = 40;
Decimal.RM = Big.roundHalfUp;

export type MoneyString = string;
export type PercentageString = string;

export function parseMoney(value: unknown): MoneyString | null {
    if (
        typeof value !== 'string' ||
        value.length > 40 ||
        !/^-?\d{1,25}(?:\.\d{1,2})?$/.test(value)
    ) {
        return null;
    }
    const amount = new Decimal(value);
    return amount.eq('0') ? '0.00' : amount.toFixed(2);
}

export function money(value: unknown): MoneyString {
    const amount = parseMoney(value);
    if (amount === null) {
        throw new TypeError('Expected an exact decimal amount string.');
    }
    return amount;
}

export const moneySchema = z
    .string()
    .refine((value) => parseMoney(value) !== null, 'Introduce un importe con hasta dos decimales.')
    .transform(money);

export function decimal(value: string): Big {
    return new Decimal(value);
}

export function addMoney(left: string, right: string): MoneyString {
    return decimal(left).plus(right).toFixed(2);
}

export function subtractMoney(left: string, right: string): MoneyString {
    return decimal(left).minus(right).toFixed(2);
}

export function sumMoney(values: Iterable<string>): MoneyString {
    let result = decimal('0');
    for (const value of values) result = result.plus(value);
    return result.toFixed(2);
}

export function compareMoney(left: string, right: string): number {
    return decimal(left).cmp(right);
}

export function absMoney(value: string): MoneyString {
    return decimal(value).abs().toFixed(2);
}

export function minMoney(left: string, right: string): MoneyString {
    return compareMoney(left, right) <= 0 ? left : right;
}

export function maxMoney(left: string, right: string): MoneyString {
    return compareMoney(left, right) >= 0 ? left : right;
}

export function formatMoney(value: string): string {
    const normalized = money(value);
    const negative = normalized.startsWith('-');
    const [whole, fraction] = normalized.replace('-', '').split('.');
    const grouped = (whole ?? '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return `${negative ? '-' : ''}$${grouped}.${fraction}`;
}

export function moneyToChartNumber(value: string): number {
    const result = Number(value);
    if (!Number.isFinite(result)) throw new RangeError('Amount exceeds chart range.');
    return result;
}

export function percentageToString(value: number): PercentageString {
    const result = money(String(value));
    if (compareMoney(result, '0') < 0 || compareMoney(result, '100') > 0) {
        throw new RangeError('Percentage must be between zero and one hundred.');
    }
    return result;
}

export function percentageToDisplayNumber(value: unknown): number {
    const result = money(value);
    if (compareMoney(result, '0') < 0 || compareMoney(result, '100') > 0) {
        throw new RangeError('Percentage must be between zero and one hundred.');
    }
    return Number(result);
}

export const percentageSchema = moneySchema
    .refine(
        (value) => compareMoney(value, '0') >= 0 && compareMoney(value, '100') <= 0,
        'El porcentaje debe estar entre 0 y 100.',
    )
    .transform(percentageToDisplayNumber);

export function percentageToBasisPoints(value: string): number {
    const percentage = money(value);
    if (compareMoney(percentage, '0') < 0 || compareMoney(percentage, '100') > 0) {
        throw new RangeError('Percentage must be between zero and one hundred.');
    }
    return Number(decimal(percentage).times('100').toFixed(0));
}

export function divideMoney(value: string, divisor: string): MoneyString {
    const denominator = decimal(divisor);
    if (denominator.lte('0') || denominator.mod('1').cmp('0') !== 0) {
        throw new RangeError('Expected a positive integer divisor.');
    }
    const Integer = Big();
    Integer.strict = true;
    Integer.DP = 0;
    Integer.RM = Big.roundDown;
    const cents = decimal(money(value)).times('100');
    const magnitude = new Integer(cents.abs().toFixed(0));
    let quotient = magnitude.div(divisor);
    if (magnitude.mod(divisor).times('2').gte(divisor)) quotient = quotient.plus('1');
    const result = decimal(quotient.toFixed(0)).times('0.01');
    return cents.lt('0') ? result.times('-1').toFixed(2) : result.toFixed(2);
}
