import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';

import {
  accountInviteApiSchema,
  mapAccountInviteApiToDomain,
} from '../schemas/accountInviteSchemas';
import type { AccountInvite, AccountInviteWritePayload } from '../schemas/accountInviteSchemas';

const apiClient = createApiClient();
const invitesPath = '/account-invites';
const accountsPath = '/accounts';

const inviteCollectionSchema = z
  .union([
    z.array(accountInviteApiSchema),
    z.object({
      data: z.array(accountInviteApiSchema),
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

const singleInviteSchema = z
  .union([
    accountInviteApiSchema,
    z.object({
      data: accountInviteApiSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

function buildWritePayload(payload: AccountInviteWritePayload) {
  return {
    email: payload.email,
    percentage: payload.percentage,
    status: payload.status,
  };
}

export function createAccountInvitesRepository() {
  return {
    async list(accountId: string): Promise<AccountInvite[]> {
      const response = await apiClient.get<unknown>(`${accountsPath}/${accountId}/invites`);
      return inviteCollectionSchema.parse(response).map(mapAccountInviteApiToDomain);
    },
    async create(payload: AccountInviteWritePayload): Promise<AccountInvite> {
      const response = await apiClient.post<unknown>(
        `${accountsPath}/${payload.accountId}/invites`,
        buildWritePayload(payload),
      );
      return mapAccountInviteApiToDomain(singleInviteSchema.parse(response));
    },
    async update(inviteId: string, payload: AccountInviteWritePayload): Promise<AccountInvite> {
      const response = await apiClient.put<unknown>(
        `${accountsPath}/${payload.accountId}/invites/${inviteId}`,
        buildWritePayload(payload),
      );
      return mapAccountInviteApiToDomain(singleInviteSchema.parse(response));
    },
    async remove(inviteId: string, accountId?: string): Promise<void> {
      if (accountId) {
        await apiClient.delete(`${accountsPath}/${accountId}/invites/${inviteId}`);
        return;
      }

      await apiClient.delete(`${invitesPath}/${inviteId}`);
    },
  };
}
