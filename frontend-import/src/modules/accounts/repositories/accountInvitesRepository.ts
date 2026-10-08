import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';
import {
  createPaginatedCollectionSchema,
  type PaginatedCollection,
} from '@/modules/shared/lib/pagination';
import { buildQueryParams } from '@/modules/shared/lib/queryParams';

import {
  accountInviteApiSchema,
  mapAccountInviteApiToDomain,
} from '../schemas/accountInviteSchemas';
import type {
  AccountInvite,
  AccountInviteListFilters,
  AccountInviteWritePayload,
} from '../schemas/accountInviteSchemas';

const apiClient = createApiClient();
const invitesPath = '/account-invites';
const accountsPath = '/accounts';

const inviteCollectionSchema = createPaginatedCollectionSchema(accountInviteApiSchema);

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

function buildGlobalWritePayload(payload: AccountInviteWritePayload) {
  return {
    account_id: Number(payload.accountId),
    email: payload.email,
    percentage: payload.percentage,
    status: payload.status,
  };
}

export function createAccountInvitesRepository() {
  return {
    async listAll(options?: {
      page?: number;
      perPage?: number;
      filters?: AccountInviteListFilters;
    }): Promise<PaginatedCollection<AccountInvite>> {
      const searchParams = buildQueryParams({
        page: options?.page,
        per_page: options?.perPage,
        search: options?.filters?.search,
        status: options?.filters?.status,
      });
      const query = searchParams.toString();
      const response = await apiClient.get<unknown>(
        query ? `${invitesPath}?${query}` : invitesPath,
      );
      const parsedResponse = inviteCollectionSchema.parse(response);

      return {
        ...parsedResponse,
        items: parsedResponse.items.map(mapAccountInviteApiToDomain),
      };
    },
    async list(
      accountId: string,
      options?: { page?: number; perPage?: number; filters?: AccountInviteListFilters },
    ): Promise<PaginatedCollection<AccountInvite>> {
      const searchParams = buildQueryParams({
        page: options?.page,
        per_page: options?.perPage,
        search: options?.filters?.search,
        status: options?.filters?.status,
      });
      const query = searchParams.toString();
      const response = await apiClient.get<unknown>(
        query
          ? `${accountsPath}/${accountId}/invites?${query}`
          : `${accountsPath}/${accountId}/invites`,
      );
      const parsedResponse = inviteCollectionSchema.parse(response);

      return {
        ...parsedResponse,
        items: parsedResponse.items.map(mapAccountInviteApiToDomain),
      };
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
    async resend(inviteId: string, payload: AccountInviteWritePayload): Promise<AccountInvite> {
      const response = await apiClient.put<unknown>(
        `${accountsPath}/${payload.accountId}/invites/${inviteId}`,
        buildWritePayload({ ...payload, status: 'pending' }),
      );
      return mapAccountInviteApiToDomain(singleInviteSchema.parse(response));
    },
    async respond(inviteId: string, payload: AccountInviteWritePayload): Promise<AccountInvite> {
      const response = await apiClient.put<unknown>(
        `${invitesPath}/${inviteId}`,
        buildGlobalWritePayload(payload),
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
