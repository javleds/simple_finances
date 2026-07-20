import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';
import {
  createPaginatedCollectionSchema,
  type PaginatedCollection,
} from '@/modules/shared/lib/pagination';
import { parseNullableNumber } from '@/modules/shared/lib/apiParsing';
import { buildQueryParams } from '@/modules/shared/lib/queryParams';

import {
  accountApiSchema,
  accountUserApiSchema,
  mapAccountApiToDomain,
  mapAccountUserApiToDomain,
} from '../schemas/accountSchemas';
import type {
  Account,
  AccountListFilters,
  AccountMember,
  AccountMemberTransferPayload,
  AccountMemberTransferResult,
  AccountUsersListFilters,
  AccountWritePayload,
} from '../types';

const apiClient = createApiClient();
const accountsPath = '/accounts';

const accountCollectionSchema = createPaginatedCollectionSchema(accountApiSchema);
const accountUserCollectionSchema = createPaginatedCollectionSchema(accountUserApiSchema);

const accountMemberAmountApiSchema = z
  .object({
    user_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
    user_name: z.string().catch('Usuario no disponible'),
    amount: z.unknown().transform((value) => Number(value) || 0),
  })
  .transform((payload) => ({
    userId: payload.user_id,
    userName: payload.user_name,
    amount: payload.amount,
  }));

const accountPendingReimbursementItemApiSchema = z
  .object({
    transaction_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
    concept: z.string().catch('Movimiento no disponible'),
    amount: z.unknown().transform((value) => Number(value) || 0),
    occurred_at: z
      .string()
      .nullable()
      .optional()
      .transform((value) => value ?? null),
  })
  .transform((payload) => ({
    transactionId: payload.transaction_id,
    concept: payload.concept,
    amount: payload.amount,
    occurredAt: payload.occurred_at,
  }));

const accountPendingReimbursementApiSchema = z
  .object({
    from_user_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
    from_user_name: z.string().catch('Usuario no disponible'),
    to_user_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
    to_user_name: z.string().catch('Usuario no disponible'),
    amount: z.unknown().transform((value) => Number(value) || 0),
    items: z.array(accountPendingReimbursementItemApiSchema).optional().default([]),
  })
  .transform((payload) => ({
    fromUserId: payload.from_user_id,
    fromUserName: payload.from_user_name,
    toUserId: payload.to_user_id,
    toUserName: payload.to_user_name,
    amount: payload.amount,
    items: payload.items,
  }));

const accountMemberTransferResponseSchema = z
  .object({
    meta: z.object({
      account: z
        .object({
          balance: z.unknown().transform(parseNullableNumber),
        })
        .optional(),
      custody_by_user: z.array(accountMemberAmountApiSchema).default([]),
      settlements_by_user: z.array(accountMemberAmountApiSchema).default([]),
      pending_reimbursements: z.array(accountPendingReimbursementApiSchema).default([]),
    }),
  })
  .transform<AccountMemberTransferResult>((payload) => ({
    accountBalance: payload.meta.account?.balance ?? null,
    custodyByUser: payload.meta.custody_by_user,
    settlementsByUser: payload.meta.settlements_by_user,
    pendingReimbursements: payload.meta.pending_reimbursements,
  }));

const singleAccountSchema = z
  .union([
    accountApiSchema,
    z.object({
      data: accountApiSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

const singleAccountUserSchema = z
  .union([
    accountUserApiSchema,
    z.object({
      data: accountUserApiSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

function mapWritePayloadToApi(payload: AccountWritePayload) {
  return {
    name: payload.name,
    description: payload.description,
    color: payload.color,
    virtual: payload.isVirtual,
    credit_card: payload.isCredit,
    credit_line: payload.creditLine,
    cutoff_day: payload.closingDay,
  };
}

function mapAccountKindsToApi(kinds: AccountListFilters['kind']): string[] | undefined {
  if (!kinds || kinds.length === 0 || kinds.length > 1) {
    return undefined;
  }

  return [kinds[0] === 'credit' ? 'true' : 'false'];
}

function mapAccountSurfacesToApi(surfaces: AccountListFilters['surface']): string[] | undefined {
  if (!surfaces || surfaces.length === 0 || surfaces.length > 1) {
    return undefined;
  }

  return [surfaces[0] === 'virtual' ? 'true' : 'false'];
}

export function createAccountsRepository() {
  return {
    async list(options?: {
      page?: number;
      perPage?: number;
      filters?: AccountListFilters;
    }): Promise<PaginatedCollection<Account>> {
      const searchParams = buildQueryParams({
        page: options?.page,
        per_page: options?.perPage,
        search: options?.filters?.search,
        status: options?.filters?.status,
        credit_card: mapAccountKindsToApi(options?.filters?.kind),
        virtual: mapAccountSurfacesToApi(options?.filters?.surface),
      });
      const query = searchParams.toString();
      const response = await apiClient.get<unknown>(
        query ? `${accountsPath}?${query}` : accountsPath,
      );
      const parsedResponse = accountCollectionSchema.parse(response);

      return {
        ...parsedResponse,
        items: parsedResponse.items.map(mapAccountApiToDomain),
      };
    },
    async getById(accountId: string): Promise<Account> {
      const response = await apiClient.get<unknown>(`${accountsPath}/${accountId}`);
      return mapAccountApiToDomain(singleAccountSchema.parse(response));
    },
    async listUsers(
      accountId: string,
      options?: { page?: number; perPage?: number; filters?: AccountUsersListFilters },
    ): Promise<PaginatedCollection<AccountMember>> {
      const searchParams = buildQueryParams({
        page: options?.page,
        per_page: options?.perPage,
        search: options?.filters?.search,
      });
      const query = searchParams.toString();
      const response = await apiClient.get<unknown>(
        query
          ? `${accountsPath}/${accountId}/users?${query}`
          : `${accountsPath}/${accountId}/users`,
      );
      const parsedResponse = accountUserCollectionSchema.parse(response);

      return {
        ...parsedResponse,
        items: parsedResponse.items.map(mapAccountUserApiToDomain),
      };
    },
    async updateUserPercentage(
      accountId: string,
      userId: string,
      percentage: number,
    ): Promise<AccountMember> {
      const response = await apiClient.put<unknown>(
        `${accountsPath}/${accountId}/users/${userId}`,
        {
          percentage,
        },
      );
      return mapAccountUserApiToDomain(singleAccountUserSchema.parse(response));
    },
    async removeUser(accountId: string, userId: string): Promise<void> {
      await apiClient.delete(`${accountsPath}/${accountId}/users/${userId}`);
    },
    async createMemberTransfer(
      accountId: string,
      payload: AccountMemberTransferPayload,
    ): Promise<AccountMemberTransferResult> {
      const response = await apiClient.post<unknown>(
        `${accountsPath}/${accountId}/member-transfers`,
        {
          from_user_id: payload.fromUserId,
          to_user_id: payload.toUserId,
          amount: payload.amount,
          description: payload.description,
          occurred_at: payload.occurredAt,
        },
      );

      return accountMemberTransferResponseSchema.parse(response);
    },
    async create(payload: AccountWritePayload): Promise<Account> {
      const response = await apiClient.post<unknown>(accountsPath, mapWritePayloadToApi(payload));
      return mapAccountApiToDomain(singleAccountSchema.parse(response));
    },
    async update(accountId: string, payload: AccountWritePayload): Promise<Account> {
      const response = await apiClient.put<unknown>(
        `${accountsPath}/${accountId}`,
        mapWritePayloadToApi(payload),
      );
      return mapAccountApiToDomain(singleAccountSchema.parse(response));
    },
    async remove(accountId: string): Promise<void> {
      await apiClient.delete(`${accountsPath}/${accountId}`);
    },
  };
}
