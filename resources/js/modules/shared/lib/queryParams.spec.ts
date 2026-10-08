import { describe, expect, it } from 'vitest';

import { buildQueryParams, parseQueryValues } from './queryParams';

describe('query params', () => {
  it('serializes multi-value params as comma-separated values', () => {
    const params = buildQueryParams({
      status: ['completed', 'pending'],
      type: ['income'],
      empty: [],
    });

    expect(params.toString()).toBe('status=completed%2Cpending&type=income');
    expect(params.getAll('status')).toEqual(['completed,pending']);
  });

  it('trims and skips empty items when serializing multi-value params', () => {
    const params = buildQueryParams({
      status: [' completed ', '', 'pending'],
    });

    expect(params.toString()).toBe('status=completed%2Cpending');
  });

  it('parses comma-separated route query values', () => {
    const values = parseQueryValues('completed,pending', isTransactionStatus);

    expect(values).toEqual(['completed', 'pending']);
  });

  it('parses legacy repeated route query values so filters are rewritten as comma-separated values', () => {
    const values = parseQueryValues(['completed', 'pending,ignored'], isTransactionStatus);

    expect(values).toEqual(['completed', 'pending']);
  });
});

function isTransactionStatus(value: string): value is 'completed' | 'pending' {
  return value === 'completed' || value === 'pending';
}
