export type AccountStatus = 'Activo' | 'Inactivo';

export type AccountMember = {
  id: string;
  name: string;
  email: string;
  allocationPercentage: number;
  pendingExpenses: string;
};

export type AccountType = 'credito' | 'debito';

export type AccountRecord = {
  id: string;
  color: string;
  name: string;
  description: string;
  accountType: AccountType;
  isVirtual: boolean;
  status: AccountStatus;
  balance: string;
  totalSpent: string;
  availableCredit: string;
  creditLine: string;
  fundingAccount: string | null;
  users: AccountMember[];
};

export const accounts: AccountRecord[] = [
  {
    id: 'cuenta-concentradora',
    color: '#2563EB',
    name: 'Ahorro para el retiro chcucho',
    description:
      'Cuenta principal para concentrar saldo, cubrir gastos relevantes y repartir fondos hacia cuentas satélite.',
    accountType: 'credito',
    isVirtual: false,
    status: 'Activo',
    balance: '$184,220',
    totalSpent: '$42,860',
    availableCredit: '$96,500',
    creditLine: '$140,000',
    fundingAccount: 'Facility principal',
    users: [
      {
        id: 'hugo-diaz',
        name: 'Hugo Díaz',
        email: 'hugo@empresa.com',
        allocationPercentage: 40,
        pendingExpenses: '$1,240.00',
      },
      {
        id: 'ana-ruiz',
        name: 'Ana Ruiz',
        email: 'ana@empresa.com',
        allocationPercentage: 35,
        pendingExpenses: '$0.00',
      },
      {
        id: 'luis-perez',
        name: 'Luis Pérez',
        email: 'luis@empresa.com',
        allocationPercentage: 25,
        pendingExpenses: '$420.00',
      },
    ],
  },
  {
    id: 'reserva-tributaria',
    color: '#0F766E',
    name: 'Reserva tributaria',
    description:
      'Cuenta dedicada a apartar flujo para obligaciones fiscales y pagos programados del siguiente corte.',
    accountType: 'debito',
    isVirtual: true,
    status: 'Activo',
    balance: '$62,080',
    totalSpent: '$8,420',
    availableCredit: '$31,300',
    creditLine: '$50,000',
    fundingAccount: 'Cuenta concentradora',
    users: [
      {
        id: 'sofia-mora',
        name: 'Sofía Mora',
        email: 'sofia@empresa.com',
        allocationPercentage: 50,
        pendingExpenses: '$0.00',
      },
      {
        id: 'diego-castro',
        name: 'Diego Castro',
        email: 'diego@empresa.com',
        allocationPercentage: 50,
        pendingExpenses: '$150.00',
      },
    ],
  },
  {
    id: 'pagos-nomina',
    color: '#EA580C',
    name: 'Pagos y nómina',
    description:
      'Cuenta operativa para dispersión periódica de pagos a empleados y compromisos recurrentes.',
    accountType: 'debito',
    isVirtual: false,
    status: 'Activo',
    balance: '$44,697',
    totalSpent: '$18,960',
    availableCredit: '$22,000',
    creditLine: '$40,000',
    fundingAccount: 'Cuenta concentradora',
    users: [
      {
        id: 'mario-galvez',
        name: 'Mario Gálvez',
        email: 'mario@empresa.com',
        allocationPercentage: 45,
        pendingExpenses: '$890.00',
      },
      {
        id: 'carla-lopez',
        name: 'Carla López',
        email: 'carla@empresa.com',
        allocationPercentage: 30,
        pendingExpenses: '$0.00',
      },
      {
        id: 'tania-leon',
        name: 'Tania León',
        email: 'tania@empresa.com',
        allocationPercentage: 25,
        pendingExpenses: '$210.00',
      },
    ],
  },
  {
    id: 'crecimiento-comercial',
    color: '#7C3AED',
    name: 'Crecimiento comercial',
    description:
      'Bolsa destinada a campañas, alianzas y gastos tácticos para adquisición y expansión comercial.',
    accountType: 'credito',
    isVirtual: true,
    status: 'Inactivo',
    balance: '$28,140',
    totalSpent: '$6,540',
    availableCredit: '$58,200',
    creditLine: '$80,000',
    fundingAccount: null,
    users: [
      {
        id: 'laura-villa',
        name: 'Laura Villa',
        email: 'laura@empresa.com',
        allocationPercentage: 60,
        pendingExpenses: '$0.00',
      },
      {
        id: 'pablo-serrano',
        name: 'Pablo Serrano',
        email: 'pablo@empresa.com',
        allocationPercentage: 40,
        pendingExpenses: '$95.00',
      },
    ],
  },
  {
    id: 'inversiones-liquidas',
    color: '#D97706',
    name: 'Inversiones líquidas',
    description:
      'Cuenta para resguardar liquidez temporal con acceso controlado y metas de rendimiento de corto plazo.',
    accountType: 'credito',
    isVirtual: false,
    status: 'Activo',
    balance: '$71,560',
    totalSpent: '$4,890',
    availableCredit: '$112,000',
    creditLine: '$160,000',
    fundingAccount: 'Reserva patrimonial',
    users: [
      {
        id: 'valeria-munoz',
        name: 'Valeria Muñoz',
        email: 'valeria@empresa.com',
        allocationPercentage: 70,
        pendingExpenses: '$0.00',
      },
      {
        id: 'omar-mejia',
        name: 'Omar Mejía',
        email: 'omar@empresa.com',
        allocationPercentage: 30,
        pendingExpenses: '$540.00',
      },
    ],
  },
  {
    id: 'operacion-regional',
    color: '#DC2626',
    name: 'Operación regional',
    description:
      'Cuenta de soporte para egresos en plaza, gastos imprevistos y operación distribuida de equipos regionales.',
    accountType: 'debito',
    isVirtual: false,
    status: 'Activo',
    balance: '$36,920',
    totalSpent: '$21,330',
    availableCredit: '$44,500',
    creditLine: '$65,000',
    fundingAccount: 'Cuenta concentradora',
    users: [
      {
        id: 'josefina-ramos',
        name: 'Josefina Ramos',
        email: 'josefina@empresa.com',
        allocationPercentage: 34,
        pendingExpenses: '$320.00',
      },
      {
        id: 'ricardo-nava',
        name: 'Ricardo Nava',
        email: 'ricardo@empresa.com',
        allocationPercentage: 33,
        pendingExpenses: '$0.00',
      },
      {
        id: 'erika-solano',
        name: 'Erika Solano',
        email: 'erika@empresa.com',
        allocationPercentage: 33,
        pendingExpenses: '$110.00',
      },
    ],
  },
];

export function findAccountById(accountId: string): AccountRecord | undefined {
  return accounts.find((account) => account.id === accountId);
}
