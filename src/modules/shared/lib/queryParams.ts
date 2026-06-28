export type QueryParamPrimitive = string | number | boolean;
export type QueryParamValue =
  | QueryParamPrimitive
  | null
  | undefined
  | ReadonlyArray<QueryParamPrimitive>;

export function buildQueryParams(params: Record<string, QueryParamValue>): URLSearchParams {
  const searchParams = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === undefined) {
      continue;
    }

    if (Array.isArray(value)) {
      const normalizedValues = normalizeQueryValues(value);

      if (normalizedValues.length === 0) {
        continue;
      }

      searchParams.set(key, normalizedValues.join(','));
      continue;
    }

    const normalizedValue = String(value).trim();

    if (!normalizedValue) {
      continue;
    }

    searchParams.set(key, normalizedValue);
  }

  return searchParams;
}

export function parseQueryValues<TValue extends string>(
  value: unknown,
  isAllowedValue: (value: string) => value is TValue,
): TValue[] {
  return normalizeQueryValues(value)
    .filter(isAllowedValue);
}

function normalizeQueryValues(value: unknown): string[] {
  const rawValues = Array.isArray(value) ? value : [value];
  const values: string[] = [];

  for (const rawValue of rawValues) {
    if (rawValue === null || rawValue === undefined) {
      continue;
    }

    const items = String(rawValue).split(',');

    for (const item of items) {
      const normalizedItem = item.trim();

      if (!normalizedItem) {
        continue;
      }

      values.push(normalizedItem);
    }
  }

  return values;
}

export function normalizeQueryRecord(query: Record<string, unknown>): Record<string, string> {
  return Object.entries(query).reduce<Record<string, string>>((accumulator, [key, value]) => {
    if (typeof value === 'string' && value.length > 0) {
      accumulator[key] = value;
    }

    return accumulator;
  }, {});
}

export function areQueriesEqual(
  currentQuery: Record<string, unknown>,
  nextQuery: Record<string, unknown>,
): boolean {
  const normalizedCurrent = normalizeQueryRecord(currentQuery);
  const normalizedNext = normalizeQueryRecord(nextQuery);

  return JSON.stringify(normalizedCurrent) === JSON.stringify(normalizedNext);
}
