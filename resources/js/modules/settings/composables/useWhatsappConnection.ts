import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, onBeforeUnmount, ref } from 'vue';
import { z } from 'zod';

import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';
import { getStoredAuthSession } from '@/modules/auth/lib/authSession';
import { whatsappConnectionQueryKeys } from '../queries/whatsappConnectionQueries';
import {
    createWhatsappConnectionRepository,
    type WhatsappConnection,
} from '../repositories/whatsappConnectionRepository';

const repository = createWhatsappConnectionRepository();
const phoneSchema = z.string().regex(/^\d{10}$/, 'Ingresa un teléfono de diez dígitos.');
const codeSchema = z.string().regex(/^\d{6}$/, 'Ingresa los seis dígitos del código.');

export function useWhatsappConnection() {
    const queryClient = useQueryClient();
    const queryKey = whatsappConnectionQueryKeys.detail(getStoredAuthSession()?.user.id ?? null);
    const phone = ref('');
    const code = ref('');
    const isLinkFormOpen = ref(false);
    const isUnlinkConfirmationOpen = ref(false);
    const actionError = ref<string | null>(null);
    const now = ref(Date.now());
    const clock = setInterval(() => {
        now.value = Date.now();
    }, 1000);
    onBeforeUnmount(() => clearInterval(clock));

    const connectionQuery = useQuery({
        queryKey,
        queryFn: () => repository.get(),
    });
    const mutation = useMutation({
        mutationFn: (operation: () => Promise<WhatsappConnection>) => operation(),
        onSuccess: async (connection) => {
            queryClient.setQueryData(queryKey, connection);
            await queryClient.invalidateQueries({ queryKey });
            code.value = '';
            isLinkFormOpen.value = false;
            isUnlinkConfirmationOpen.value = false;
        },
    });
    const connection = computed(() => connectionQuery.data.value);
    const isExpired = computed(() => {
        const expiry = connection.value?.expires_at;
        return !!expiry && new Date(expiry).getTime() <= now.value;
    });
    const resendSeconds = computed(() => {
        const availableAt = connection.value?.resend_available_at;
        if (!availableAt) return 0;
        return Math.max(0, Math.ceil((new Date(availableAt).getTime() - now.value) / 1000));
    });
    const error = computed(() => {
        if (actionError.value) return actionError.value;
        if (connectionQuery.error.value) {
            return resolveApiErrorMessage(
                connectionQuery.error.value,
                'No fue posible cargar WhatsApp.',
            );
        }
        return null;
    });

    async function run(operation: () => Promise<WhatsappConnection>): Promise<void> {
        if (mutation.isPending.value) return;
        actionError.value = null;
        try {
            await mutation.mutateAsync(operation);
        } catch (failure) {
            actionError.value = resolveApiErrorMessage(
                failure,
                'No fue posible actualizar WhatsApp.',
            );
            await queryClient.invalidateQueries({ queryKey });
        }
    }

    async function requestCode(): Promise<void> {
        const result = phoneSchema.safeParse(phone.value);
        if (!result.success) {
            actionError.value = result.error.issues[0]?.message ?? 'Revisa tu teléfono.';
            return;
        }
        await run(() => repository.requestCode(`+52${result.data}`));
    }

    async function resendCode(): Promise<void> {
        const phoneNumber = connection.value?.phone_number;
        if (!phoneNumber || resendSeconds.value > 0) return;
        await run(() => repository.requestCode(phoneNumber));
    }

    async function verifyCode(): Promise<void> {
        const result = codeSchema.safeParse(code.value);
        if (!result.success) {
            actionError.value = result.error.issues[0]?.message ?? 'Revisa el código.';
            return;
        }
        if (isExpired.value) {
            actionError.value = 'El código expiró. Solicita uno nuevo.';
            return;
        }
        await run(() => repository.verify(result.data));
    }

    async function unlink(): Promise<void> {
        await run(() => repository.unlink());
    }

    return {
        connection,
        phone,
        code,
        isLinkFormOpen,
        isUnlinkConfirmationOpen,
        isExpired,
        resendSeconds,
        error,
        isLoading: connectionQuery.isPending,
        isLoadError: connectionQuery.isError,
        isSaving: mutation.isPending,
        retry: connectionQuery.refetch,
        requestCode,
        resendCode,
        verifyCode,
        unlink,
    };
}
