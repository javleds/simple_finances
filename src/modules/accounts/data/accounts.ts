export type AccountStatus = 'Activo' | 'Inactivo';

export type AccountRecord = {
  id: string;
  color: string;
  name: string;
  status: AccountStatus;
  balance: string;
  totalSpent: string;
  availableCredit: string;
  creditLine: string;
  fundingAccount: string;
};

export const accounts: AccountRecord[] = [
  {
    id: 'cuenta-concentradora',
    color: '#2563EB',
    name: 'Ahorro para el retiro chcucho',
    status: 'Activo',
    balance: '$184,220',
    totalSpent: '$42,860',
    availableCredit: '$96,500',
    creditLine: '$140,000',
    fundingAccount: 'Facility principal',
  },
  {
    id: 'reserva-tributaria',
    color: '#0F766E',
    name: 'Reserva tributaria',
    status: 'Activo',
    balance: '$62,080',
    totalSpent: '$8,420',
    availableCredit: '$31,300',
    creditLine: '$50,000',
    fundingAccount: 'Cuenta concentradora',
  },
  {
    id: 'pagos-nomina',
    color: '#EA580C',
    name: 'Pagos y nómina',
    status: 'Activo',
    balance: '$44,697',
    totalSpent: '$18,960',
    availableCredit: '$22,000',
    creditLine: '$40,000',
    fundingAccount: 'Cuenta concentradora',
  },
  {
    id: 'crecimiento-comercial',
    color: '#7C3AED',
    name: 'Crecimiento comercial',
    status: 'Inactivo',
    balance: '$28,140',
    totalSpent: '$6,540',
    availableCredit: '$58,200',
    creditLine: '$80,000',
    fundingAccount: 'Facility principal',
  },
  {
    id: 'inversiones-liquidas',
    color: '#D97706',
    name: 'Inversiones líquidas',
    status: 'Activo',
    balance: '$71,560',
    totalSpent: '$4,890',
    availableCredit: '$112,000',
    creditLine: '$160,000',
    fundingAccount: 'Reserva patrimonial',
  },
  {
    id: 'operacion-regional',
    color: '#DC2626',
    name: 'Operación regional',
    status: 'Activo',
    balance: '$36,920',
    totalSpent: '$21,330',
    availableCredit: '$44,500',
    creditLine: '$65,000',
    fundingAccount: 'Cuenta concentradora',
  },
];

export function findAccountById(accountId: string): AccountRecord | undefined {
  return accounts.find((account) => account.id === accountId);
}
