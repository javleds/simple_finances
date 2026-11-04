import { z } from 'zod';

export type PaginatedCollection<T> = {
  items: T[];
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number | null;
  hasMore: boolean;
};

const paginationMetaSchema = z
  .object({
    current_page: z.coerce.number().optional(),
    last_page: z.coerce.number().optional(),
    per_page: z.coerce.number().optional(),
    total: z.coerce.number().optional(),
  })
  .partial();

export function createPaginatedCollectionSchema<TSchema extends z.ZodTypeAny>(itemSchema: TSchema) {
  return z
    .union([
      z.array(itemSchema),
      z
        .object({
          data: z.array(itemSchema),
          meta: paginationMetaSchema.optional(),
          current_page: z.coerce.number().optional(),
          last_page: z.coerce.number().optional(),
          per_page: z.coerce.number().optional(),
          total: z.coerce.number().optional(),
        })
        .passthrough(),
    ])
    .transform<PaginatedCollection<z.infer<TSchema>>>((payload) => {
      if (Array.isArray(payload)) {
        return {
          items: payload,
          currentPage: 1,
          lastPage: 1,
          perPage: payload.length,
          total: payload.length,
          hasMore: false,
        };
      }

      const currentPage = payload.meta?.current_page ?? payload.current_page ?? 1;
      const lastPage = payload.meta?.last_page ?? payload.last_page ?? currentPage;
      const perPage = payload.meta?.per_page ?? payload.per_page ?? payload.data.length;
      const total = payload.meta?.total ?? payload.total ?? null;

      return {
        items: payload.data,
        currentPage,
        lastPage,
        perPage,
        total,
        hasMore: currentPage < lastPage,
      };
    });
}
