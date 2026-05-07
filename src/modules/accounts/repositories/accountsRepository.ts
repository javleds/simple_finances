import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';

import {
  accountApiSchema,
  accountUserApiSchema,
  mapAccountApiToDomain,
  mapAccountUserApiToDomain,
} from '../schemas/accountSchemas';
import type { Account, AccountMember, AccountWritePayload } from '../types';

const apiClient = createApiClient();
const accountsPath = '/accounts';

const accountCollectionSchema = z
  .union([
    z.array(accountApiSchema),
    z.object({
      data: z.array(accountApiSchema),
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

const singleAccountSchema = z
  .union([
    accountApiSchema,
    z.object({
      data: accountApiSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

const accountUserCollectionSchema = z
  .union([
    z.array(accountUserApiSchema),
    z.object({
      data: z.array(accountUserApiSchema),
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

export function createAccountsRepository() {
  return {
    async list(): Promise<Account[]> {
      const response = await apiClient.get<unknown>(accountsPath);
      return accountCollectionSchema.parse(response).map(mapAccountApiToDomain);
    },
    async getById(accountId: string): Promise<Account> {
      const response = await apiClient.get<unknown>(`${accountsPath}/${accountId}`);
      return mapAccountApiToDomain(singleAccountSchema.parse(response));
    },
    async listUsers(accountId: string): Promise<AccountMember[]> {
      const response = await apiClient.get<unknown>(`${accountsPath}/${accountId}/users`);
      return accountUserCollectionSchema.parse(response).map(mapAccountUserApiToDomain);
    },
    async updateUserPercentage(accountId: string, userId: string, percentage: number): Promise<AccountMember> {
      const response = await apiClient.put<unknown>(`${accountsPath}/${accountId}/users/${userId}`, {
        percentage,
      });
      return mapAccountUserApiToDomain(singleAccountUserSchema.parse(response));
    },
    async removeUser(accountId: string, userId: string): Promise<void> {
      await apiClient.delete(`${accountsPath}/${accountId}/users/${userId}`);
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
