export type TransactionPeriod = { startDate: string; endDate: string };

function formatDate(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function currentMonthRange(): TransactionPeriod {
  const today = new Date();
  return {
    startDate: formatDate(new Date(today.getFullYear(), today.getMonth(), 1)),
    endDate: formatDate(new Date(today.getFullYear(), today.getMonth() + 1, 0)),
  };
}

function isValidDate(value: string | null): value is string {
  if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const [year, month, day] = value.split('-').map(Number);
  return formatDate(new Date(year!, month! - 1, day!)) === value;
}

export function validateTransactionPeriod(
  startDate: string | null,
  endDate: string | null,
): string | null {
  if (!isValidDate(startDate) || !isValidDate(endDate)) {
    return 'Selecciona una fecha inicial y final válidas.';
  }
  if (startDate > endDate) return 'La fecha inicial debe ser anterior a la final.';
  return null;
}
