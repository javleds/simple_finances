import { describe, expect, it } from 'vitest';
import {
    divideMoney,
    addMoney,
    subtractMoney,
    sumMoney,
    compareMoney,
    formatMoney,
    money,
    moneySchema,
    parseMoney,
    percentageSchema,
    percentageToString,
} from './money';

describe('exact monetary values', () => {
    it('preserves large values and decimal cents through arithmetic and formatting', () => {
        expect(addMoney('9007199254740993.10', '0.20')).toBe('9007199254740993.30');
        expect(subtractMoney('9007199254740993.30', '0.20')).toBe('9007199254740993.10');
        expect(formatMoney('9007199254740993.10')).toBe('$9,007,199,254,740,993.10');
        expect(sumMoney(['0.10', '0.20', '-0.30'])).toBe('0.00');
    });
    it('rounds divisions once using exact quotient and remainder', () => {
        expect(divideMoney('1.00', '3')).toBe('0.33');
        expect(divideMoney('0.03', '2')).toBe('0.02');
        expect(divideMoney('-0.03', '2')).toBe('-0.02');
        expect(() => divideMoney('1.00', '0')).toThrow('Expected a positive integer divisor.');
    });
    it('compares numerically and keeps negative values', () => {
        expect(compareMoney('10.00', '2.00')).toBe(1);
        expect(formatMoney('-12.30')).toBe('-$12.30');
        expect(money('-0.00')).toBe('0.00');
        expect(money('001.2')).toBe('1.20');
    });
    it.each([
        0.1,
        null,
        undefined,
        '',
        'NaN',
        'Infinity',
        '1e3',
        '1,000.00',
        '1.001',
        '1.2.3',
        '9'.repeat(100),
    ])('rejects invalid or approximate input %s', (value) => {
        expect(parseMoney(value)).toBeNull();
        expect(moneySchema.safeParse(value).success).toBe(false);
    });
    it('maps bounded percentages between exact transport and slider presentation', () => {
        expect(percentageSchema.parse('33.33')).toBe(33.33);
        expect(percentageToString(33.33)).toBe('33.33');
        expect(percentageSchema.safeParse('100.01').success).toBe(false);
        expect(percentageSchema.safeParse(33.33).success).toBe(false);
    });
});
