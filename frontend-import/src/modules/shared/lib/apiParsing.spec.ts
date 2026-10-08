import { describe, expect, it } from 'vitest';

import { parseBooleanLike, parseEntityId, parseNullableNumber } from './apiParsing';

describe('api parsing helpers', () => {
  it('parses numeric API values without leaking formatted strings into domain state', () => {
    expect(parseNullableNumber('9,500.25')).toBe(9500.25);
    expect(parseNullableNumber('$ 1,200.00')).toBe(1200);
    expect(parseNullableNumber(45.5)).toBe(45.5);
    expect(parseNullableNumber('')).toBeNull();
    expect(parseNullableNumber(Number.NaN)).toBeNull();
  });

  it('normalizes nullable entity ids to frontend string ids', () => {
    expect(parseEntityId(123)).toBe('123');
    expect(parseEntityId('abc')).toBe('abc');
    expect(parseEntityId(null)).toBeNull();
    expect(parseEntityId({ id: 1 })).toBeNull();
  });

  it('parses boolean-like API values from legacy and JSON payloads', () => {
    expect(parseBooleanLike(true)).toBe(true);
    expect(parseBooleanLike(1)).toBe(true);
    expect(parseBooleanLike('true')).toBe(true);
    expect(parseBooleanLike('yes')).toBe(true);
    expect(parseBooleanLike(false)).toBe(false);
    expect(parseBooleanLike(0)).toBe(false);
  });
});
