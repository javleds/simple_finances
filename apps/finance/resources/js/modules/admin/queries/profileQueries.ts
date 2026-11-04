export const profileQueryKeys = {
  all: ['profile'] as const,
  detail() {
    return [...this.all, 'detail'] as const;
  },
};
