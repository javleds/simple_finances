import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    name: 'admin.accounts',
    path: 'accounts',
    component: () => import('./pages/AccountsPage.vue'),
  },
  {
    path: 'accounts/:accountId',
    component: () => import('./pages/AccountRelationshipsPage.vue'),
    children: [
      {
        path: '',
        redirect: {
          name: 'admin.accounts.transactions',
        },
      },
      {
        name: 'admin.accounts.transactions',
        path: 'transactions',
        component: () => import('./pages/AccountTransactionsPage.vue'),
      },
      {
        name: 'admin.accounts.ledger',
        path: 'ledger',
        component: () => import('./pages/AccountLedgerPage.vue'),
      },
      {
        name: 'admin.accounts.goals',
        path: 'goals',
        component: () => import('./pages/AccountGoalsPage.vue'),
      },
      {
        name: 'admin.accounts.invitations',
        path: 'invitations',
        component: () => import('./pages/AccountInvitationsPage.vue'),
      },
      {
        name: 'admin.accounts.users',
        path: 'users',
        component: () => import('./pages/AccountUsersPage.vue'),
      },
    ],
  },
];

export default routes;
