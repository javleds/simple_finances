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
  AccountLedgerDiagnosticsResult,
  AccountLedgerRepair,
  AccountLedgerRepairPayload,
  AccountLedgerResult,
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
    action_type: z
      .enum(['user_to_user', 'custody_to_user', 'user_to_account'])
      .optional()
      .default('user_to_user'),
    items: z.array(accountPendingReimbursementItemApiSchema).optional().default([]),
  })
  .transform((payload) => ({
    fromUserId: payload.from_user_id,
    fromUserName: payload.from_user_name,
    toUserId: payload.to_user_id,
    toUserName: payload.to_user_name,
    amount: payload.amount,
    actionType: payload.action_type,
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
      ledger_rows: z.array(z.unknown()).optional().default([]),
    }),
  })
  .transform<AccountMemberTransferResult>((payload) => ({
    accountBalance: payload.meta.account?.balance ?? null,
    custodyByUser: payload.meta.custody_by_user,
    settlementsByUser: payload.meta.settlements_by_user,
    pendingReimbursements: payload.meta.pending_reimbursements,
    ledgerRows: payload.meta.ledger_rows as AccountMemberTransferResult['ledgerRows'],
  }));

const accountLedgerAllocationApiSchema = z
  .object({
    user_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
    user_name: z.string().nullable().optional().default(null),
    amount: z.unknown().transform((value) => Number(value) || 0),
    percentage: z.unknown().transform((value) => Number(value) || 0),
  })
  .transform((payload) => ({
    userId: payload.user_id,
    userName: payload.user_name,
    amount: payload.amount,
    percentage: payload.percentage,
  }));

const accountLedgerRowApiSchema = z
  .object({
    id: z.union([z.string(), z.number()]).transform((value) => String(value)),
    occurred_at: z.string().nullable().optional().default(null),
    source_type: z.string(),
    transaction_id: z
      .union([z.string(), z.number()])
      .nullable()
      .optional()
      .transform((value) => (value == null ? null : String(value))),
    label: z.string().catch('Movimiento'),
    description: z.string().catch(''),
    amount: z.unknown().transform((value) => Number(value) || 0),
    balance_after: z.unknown().transform((value) => Number(value) || 0),
    custody_after_by_user: z.array(accountMemberAmountApiSchema).default([]),
    settlement_after_by_user: z.array(accountMemberAmountApiSchema).default([]),
    allocations: z.array(accountLedgerAllocationApiSchema).default([]),
  })
  .transform((payload) => ({
    id: payload.id,
    occurredAt: payload.occurred_at,
    sourceType: payload.source_type,
    transactionId: payload.transaction_id,
    label: payload.label,
    description: payload.description,
    amount: payload.amount,
    balanceAfter: payload.balance_after,
    custodyAfterByUser: payload.custody_after_by_user,
    settlementAfterByUser: payload.settlement_after_by_user,
    allocations: payload.allocations,
  }));

const accountLedgerCollectionSchema = createPaginatedCollectionSchema(accountLedgerRowApiSchema);

const accountLedgerRepairPreviewEntryApiSchema = z
  .object({
    user_id: z
      .union([z.string(), z.number()])
      .nullable()
      .optional()
      .transform((value) => (value == null ? null : String(value))),
    user_name: z.string().nullable().optional().default(null),
    related_user_id: z
      .union([z.string(), z.number()])
      .nullable()
      .optional()
      .transform((value) => (value == null ? null : String(value))),
    related_user_name: z.string().nullable().optional().default(null),
    transaction_id: z
      .union([z.string(), z.number()])
      .nullable()
      .optional()
      .transform((value) => (value == null ? null : String(value))),
    type: z.string().catch('ledger_correction'),
    amount: z.unknown().transform((value) => Number(value) || 0),
    description: z.string().nullable().optional().default(null),
  })
  .transform((payload) => ({
    userId: payload.user_id ?? '',
    userName: payload.user_name,
    relatedUserId: payload.related_user_id,
    relatedUserName: payload.related_user_name,
    transactionId: payload.transaction_id,
    type: payload.type,
    amount: payload.amount,
    description: payload.description,
  }));

const accountLedgerRepairPreviewApiSchema = z
  .object({
    summary: z.string().catch('Corrección del libro'),
    ledger_entries: z.array(accountLedgerRepairPreviewEntryApiSchema).optional().default([]),
  })
  .catch({
    summary: 'Corrección del libro',
    ledger_entries: [],
  })
  .transform((payload) => ({
    summary: payload.summary,
    ledgerEntries: payload.ledger_entries,
  }));

const accountLedgerRepairPayloadApiSchema = z
  .object({
    diagnostic_id: z.string().optional(),
    issue_code: z.string(),
    repair_type: z.enum(['settlement_correction', 'custody_correction']),
    from_user_id: z
      .union([z.string(), z.number()])
      .optional()
      .transform((value) => (value == null ? undefined : String(value))),
    to_user_id: z
      .union([z.string(), z.number()])
      .optional()
      .transform((value) => (value == null ? undefined : String(value))),
    user_id: z
      .union([z.string(), z.number()])
      .optional()
      .transform((value) => (value == null ? undefined : String(value))),
    transaction_id: z
      .union([z.string(), z.number()])
      .nullable()
      .optional()
      .transform((value) => (value == null ? null : String(value))),
    amount: z.unknown().transform((value) => Number(value) || 0),
    description: z.string().catch('Corrección del libro'),
  })
  .transform<AccountLedgerRepairPayload>((payload) => ({
    diagnosticId: payload.diagnostic_id,
    issueCode: payload.issue_code,
    repairType: payload.repair_type,
    fromUserId: payload.from_user_id,
    toUserId: payload.to_user_id,
    userId: payload.user_id,
    transactionId: payload.transaction_id,
    amount: payload.amount,
    description: payload.description,
  }));

const recordSchema = z.record(z.string(), z.unknown()).catch({});

const accountLedgerDiagnosticApiSchema = z
  .object({
    id: z.string(),
    code: z.string(),
    severity: z.string().catch('warning'),
    confidence: z.string().catch('high'),
    mode: z.string().catch('automatic'),
    repair_type: z.enum(['settlement_correction', 'custody_correction']),
    title: z.string().catch('Corrección disponible'),
    description: z.string().catch(''),
    target_transaction_id: z
      .union([z.string(), z.number()])
      .nullable()
      .optional()
      .transform((value) => (value == null ? null : String(value))),
    evidence: recordSchema,
    preview: accountLedgerRepairPreviewApiSchema,
    suggested_payload: accountLedgerRepairPayloadApiSchema,
    required_fields: z.array(z.string()).optional().default([]),
  })
  .transform((payload) => ({
    id: payload.id,
    code: payload.code,
    severity: payload.severity,
    confidence: payload.confidence,
    mode: payload.mode,
    repairType: payload.repair_type,
    title: payload.title,
    description: payload.description,
    targetTransactionId: payload.target_transaction_id,
    evidence: payload.evidence,
    preview: payload.preview,
    suggestedPayload: {
      ...payload.suggested_payload,
      diagnosticId: payload.id,
      evidence: payload.evidence,
      preview: payload.preview as unknown as Record<string, unknown>,
    },
    requiredFields: payload.required_fields,
  }));

const accountLedgerRepairApiSchema = z
  .object({
    id: z.union([z.string(), z.number()]).transform((value) => String(value)),
    status: z.string(),
    issue_code: z.string(),
    repair_type: z.enum(['settlement_correction', 'custody_correction']),
    confidence: z.string(),
    actor_user_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
    actor_user_name: z.string().catch('Usuario no disponible'),
    target_transaction_id: z
      .union([z.string(), z.number()])
      .nullable()
      .optional()
      .transform((value) => (value == null ? null : String(value))),
    target_transaction_concept: z.string().nullable().optional().default(null),
    description: z.string().catch('Corrección del libro'),
    amount: z.unknown().transform((value) => Number(value) || 0),
    created_at: z.string().nullable().optional().default(null),
    can_reverse: z.boolean().catch(false),
    preview: accountLedgerRepairPreviewApiSchema,
    result: recordSchema,
  })
  .transform<AccountLedgerRepair>((payload) => ({
    id: payload.id,
    status: payload.status,
    issueCode: payload.issue_code,
    repairType: payload.repair_type,
    confidence: payload.confidence,
    actorUserId: payload.actor_user_id,
    actorUserName: payload.actor_user_name,
    targetTransactionId: payload.target_transaction_id,
    targetTransactionConcept: payload.target_transaction_concept,
    description: payload.description,
    amount: payload.amount,
    createdAt: payload.created_at,
    canReverse: payload.can_reverse,
    preview: payload.preview,
    result: payload.result,
  }));

const accountLedgerDiagnosticsResponseSchema = z
  .object({
    data: z.object({
      diagnostics: z.array(accountLedgerDiagnosticApiSchema).default([]),
      repairs: z.array(accountLedgerRepairApiSchema).default([]),
    }),
  })
  .transform<AccountLedgerDiagnosticsResult>((payload) => payload.data);

const accountLedgerRepairResponseSchema = z
  .object({
    data: accountLedgerRepairApiSchema,
  })
  .transform<AccountLedgerRepair>((payload) => payload.data);

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

function mapRepairPreviewToApi(preview: AccountLedgerRepairPayload['preview']) {
  if (!preview || !('ledgerEntries' in preview)) {
    return preview;
  }

  const typedPreview = preview as unknown as {
    summary?: string;
    ledgerEntries?: Array<{
      userId?: string;
      userName?: string | null;
      relatedUserId?: string | null;
      relatedUserName?: string | null;
      transactionId?: string | null;
      type?: string;
      amount?: number;
      description?: string | null;
    }>;
  };

  return {
    summary: typedPreview.summary,
    ledger_entries: typedPreview.ledgerEntries?.map((entry) => ({
      user_id: entry.userId,
      user_name: entry.userName,
      related_user_id: entry.relatedUserId,
      related_user_name: entry.relatedUserName,
      transaction_id: entry.transactionId,
      type: entry.type,
      amount: entry.amount,
      description: entry.description,
    })),
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

export function mapAccountStatusesToDeletedAtFilter(
  statuses: AccountListFilters['status'],
): string[] | undefined {
  if (!statuses || statuses.length === 0 || statuses.length > 1) {
    return undefined;
  }

  return [statuses[0] === 'Inactivo' ? 'true' : 'false'];
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
        deleted_at: mapAccountStatusesToDeletedAtFilter(options?.filters?.status),
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
    async updateUsersPercentages(
      accountId: string,
      percentages: Record<string, number>,
    ): Promise<AccountMember[]> {
      const response = await apiClient.put<unknown>(`${accountsPath}/${accountId}/users`, {
        users: Object.entries(percentages).map(([userId, percentage]) => ({
          user_id: Number(userId),
          percentage,
        })),
      });
      const payload = z.object({ data: z.array(accountUserApiSchema) }).parse(response);
      return payload.data.map(mapAccountUserApiToDomain);
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
          action_type: payload.actionType,
          amount: payload.amount,
          description: payload.description,
          occurred_at: payload.occurredAt,
        },
      );

      return accountMemberTransferResponseSchema.parse(response);
    },
    async listLedger(
      accountId: string,
      options?: { page?: number; perPage?: number },
    ): Promise<AccountLedgerResult> {
      const searchParams = buildQueryParams({
        page: options?.page,
        per_page: options?.perPage,
      });
      const query = searchParams.toString();
      const response = await apiClient.get<unknown>(
        query ? `${accountsPath}/${accountId}/ledger?${query}` : `${accountsPath}/${accountId}/ledger`,
      );

      return accountLedgerCollectionSchema.parse(response);
    },
    async listLedgerDiagnostics(accountId: string): Promise<AccountLedgerDiagnosticsResult> {
      const response = await apiClient.get<unknown>(`${accountsPath}/${accountId}/ledger/diagnostics`);

      return accountLedgerDiagnosticsResponseSchema.parse(response);
    },
    async repairLedger(
      accountId: string,
      payload: AccountLedgerRepairPayload,
    ): Promise<AccountLedgerRepair> {
      const response = await apiClient.post<unknown>(`${accountsPath}/${accountId}/ledger/repairs`, {
        diagnostic_id: payload.diagnosticId,
        issue_code: payload.issueCode,
        repair_type: payload.repairType,
        from_user_id: payload.fromUserId,
        to_user_id: payload.toUserId,
        user_id: payload.userId,
        transaction_id: payload.transactionId,
        amount: payload.amount,
        description: payload.description,
        evidence: payload.evidence,
        preview: mapRepairPreviewToApi(payload.preview),
      });

      return accountLedgerRepairResponseSchema.parse(response);
    },
    async reverseLedgerRepair(accountId: string, repairId: string): Promise<AccountLedgerRepair> {
      const response = await apiClient.post<unknown>(
        `${accountsPath}/${accountId}/ledger/repairs/${repairId}/reverse`,
      );

      return accountLedgerRepairResponseSchema.parse(response);
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
