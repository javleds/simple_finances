<script setup lang="ts">
import Message from 'primevue/message';
import { computed } from 'vue';
import { Form, FormField, type FormSubmitEvent } from '@primevue/forms';
import { zodResolver } from '@primevue/forms/resolvers/zod';
import { z } from 'zod';
import InputText from 'primevue/inputtext';
import InputOtp from 'primevue/inputotp';

import { AppButton, AppCard, AppModal, AppText, AppTitle } from '@/modules/shared/components';
import { useWhatsappConnection } from '../composables/useWhatsappConnection';
import type { AppModalAction } from '@/modules/shared/types/modal';

const {
    connection,
    phone,
    code,
    isLinkFormOpen,
    isUnlinkConfirmationOpen,
    isExpired,
    resendSeconds,
    error,
    isLoading,
    isLoadError,
    isSaving,
    retry,
    requestCode,
    resendCode,
    verifyCode,
    unlink,
} = useWhatsappConnection();

const phoneResolver = zodResolver(
    z.object({ phone: z.string().regex(/^\d{10}$/, 'Ingresa un teléfono de diez dígitos.') }),
);
const codeResolver = zodResolver(
    z.object({ code: z.string().regex(/^\d{6}$/, 'Ingresa los seis dígitos del código.') }),
);

function submitPhone(event: FormSubmitEvent): void {
    if (event.valid) void requestCode();
}

function submitCode(event: FormSubmitEvent): void {
    if (event.valid) void verifyCode();
}

const expirationLabel = computed(() => {
    if (!connection.value?.expires_at) return '';
    return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(
        new Date(connection.value.expires_at),
    );
});
const phoneActions = computed<AppModalAction[]>(() => [
    {
        key: 'cancel',
        label: 'Cancelar',
        tone: 'neutral',
        autoClose: true,
        disabled: isSaving.value,
    },
    {
        key: 'submit',
        label: 'Enviar código',
        tone: 'primary',
        type: 'submit',
        form: 'whatsapp-phone-form',
        loading: isSaving.value,
    },
]);

const unlinkActions = computed<AppModalAction[]>(() => [
    {
        key: 'cancel',
        label: 'Cancelar',
        tone: 'neutral',
        autoClose: true,
        disabled: isSaving.value,
    },
    { key: 'unlink', label: 'Desvincular', tone: 'danger', loading: isSaving.value },
]);

function handleUnlinkAction(action: string): void {
    if (action === 'unlink') void unlink();
}
</script>

<template>
    <AppCard class="rounded-2xl sm:rounded-3xl" aria-labelledby="whatsapp-title">
        <div class="space-y-4">
            <div class="space-y-1">
                <AppTitle id="whatsapp-title" as="h2" size="sm">WhatsApp</AppTitle>
                <AppText>Vincula tu número de WhatsApp con tu cuenta.</AppText>
            </div>

            <AppText v-if="isLoading" role="status">Cargando WhatsApp...</AppText>
            <Message v-if="error" role="alert" class="text-(--app-color-danger)" severity="error">{{
                error
            }}</Message>
            <AppButton v-if="isLoadError" variant="outline" @click="void retry()"
                >Reintentar</AppButton
            >

            <template v-if="!isLoading && !isLoadError && connection">
                <div v-if="connection.status === 'linked'" class="space-y-3">
                    <AppText role="status">Número vinculado: {{ connection.phone_number }}</AppText>
                    <AppButton variant="outline" @click="isUnlinkConfirmationOpen = true">
                        Desvincular WhatsApp
                    </AppButton>
                </div>

                <div v-else-if="connection.status === 'pending'" class="space-y-4">
                    <AppText role="status"
                        >Enviamos un código a {{ connection.phone_number }}.</AppText
                    >
                    <AppText v-if="isExpired">El código expiró. Solicita uno nuevo.</AppText>
                    <AppText v-else size="sm"
                        >Válido hasta {{ expirationLabel }} (24 horas).</AppText
                    >

                    <Form
                        :key="connection.expires_at ?? 'pending'"
                        :resolver="codeResolver"
                        class="space-y-3"
                        @submit="submitCode"
                    >
                        <fieldset :disabled="isSaving || isExpired" class="space-y-2">
                            <legend class="text-sm font-medium text-(--app-color-label)">
                                Código de verificación
                            </legend>
                            <FormField v-slot="$field" name="code" :initial-value="code">
                                <InputOtp
                                    v-model="code"
                                    @update:model-value="$field.props.onChange({ value: $event })"
                                    :length="6"
                                    integerOnly
                                    :disabled="isSaving || isExpired"
                                    :pt="{
                                        pcInputText: {
                                            root: {
                                                'aria-label': 'Dígito del código de verificación',
                                                autocomplete: 'one-time-code',
                                            },
                                        },
                                    }"
                                    class="gap-1.5 sm:gap-2"
                                />
                                <Message
                                    v-if="$field.invalid"
                                    severity="error"
                                    size="small"
                                    variant="simple"
                                    >{{ $field.error?.message }}</Message
                                >
                            </FormField>
                        </fieldset>
                        <AppButton
                            type="submit"
                            :disabled="isExpired || code.length !== 6"
                            :loading="isSaving"
                        >
                            Verificar número
                        </AppButton>
                    </Form>
                    <div class="flex flex-wrap gap-2">
                        <AppButton
                            variant="outline"
                            :disabled="resendSeconds > 0"
                            :loading="isSaving"
                            @click="resendCode"
                        >
                            {{
                                resendSeconds > 0
                                    ? `Reenviar en ${resendSeconds} s`
                                    : 'Reenviar código'
                            }}
                        </AppButton>
                        <AppButton
                            variant="ghost"
                            :disabled="isSaving"
                            @click="isLinkFormOpen = !isLinkFormOpen"
                        >
                            Usar otro número
                        </AppButton>
                    </div>
                </div>

                <AppButton v-else-if="!isLinkFormOpen" @click="isLinkFormOpen = true">
                    Ligar cuenta con WhatsApp
                </AppButton>

                <AppModal
                    :open="isLinkFormOpen && connection.status !== 'linked'"
                    title="Vincular WhatsApp"
                    presentation="sheet"
                    :actions="phoneActions"
                    @close="isLinkFormOpen = false"
                >
                    <Form
                        :resolver="phoneResolver"
                        id="whatsapp-phone-form"
                        class="space-y-4"
                        @submit="submitPhone"
                    >
                        <Message v-if="error" severity="error" role="alert">{{ error }}</Message>
                        <label
                            for="whatsapp-phone"
                            class="block text-sm font-medium text-(--app-color-label)"
                            >Número de teléfono</label
                        >
                        <FormField v-slot="$field" name="phone" :initial-value="phone">
                            <div class="flex max-w-sm items-center gap-3">
                                <span class="shrink-0 font-medium text-(--app-color-text)"
                                    >+52</span
                                >
                                <InputText
                                    id="whatsapp-phone"
                                    v-model="phone"
                                    @update:model-value="$field.props.onChange({ value: $event })"
                                    type="tel"
                                    inputmode="numeric"
                                    autocomplete="tel-national"
                                    pattern="[0-9]{10}"
                                    maxlength="10"
                                    required
                                    aria-describedby="whatsapp-phone-help"
                                    :disabled="isSaving"
                                    class="min-w-0 flex-1"
                                />
                            </div>
                            <Message
                                v-if="$field.invalid"
                                severity="error"
                                size="small"
                                variant="simple"
                                >{{ $field.error?.message }}</Message
                            >
                        </FormField>
                        <AppText id="whatsapp-phone-help" size="sm"
                            >México (+52). Ingresa diez dígitos.</AppText
                        >
                    </Form>
                </AppModal>
            </template>

            <AppModal
                :open="isUnlinkConfirmationOpen"
                title="Desvincular WhatsApp"
                variant="warning"
                :actions="unlinkActions"
                @close="isUnlinkConfirmationOpen = false"
                @action="handleUnlinkAction"
            >
                <AppText
                    >¿Quieres desvincular {{ connection?.phone_number }}? Para vincularlo de nuevo
                    deberás verificar otro código.</AppText
                >
                <Message
                    v-if="error"
                    role="alert"
                    class="mt-3 text-(--app-color-danger)"
                    severity="error"
                    >{{ error }}</Message
                >
            </AppModal>
        </div>
    </AppCard>
</template>
