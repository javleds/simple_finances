export const whatsappConnectionQueryKeys = {
    detail: (userId: string | number | null) => ['whatsapp-connection', userId, 'detail'] as const,
};
