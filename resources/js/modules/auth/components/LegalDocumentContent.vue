<script setup lang="ts">
import { computed } from 'vue';
import { useLegalDocuments } from '@/modules/auth/legal/legalDocuments';
import { AppButton, AppText, AppTitle } from '@/modules/shared/components';
const props = withDefaults(defineProps<{ kind: 'terms' | 'privacy'; embedded?: boolean }>(), {
    embedded: false,
});
const { data, isPending, isError, refetch } = useLegalDocuments();
const document = computed(() => data.value?.documents[props.kind]);
</script>
<template>
    <div class="space-y-5">
        <AppText v-if="isPending" role="status">Cargando documento…</AppText>
        <div v-else-if="isError || !data?.available" role="alert" class="space-y-3">
            <AppText
                >La información legal no está disponible temporalmente. Intenta nuevamente.</AppText
            >
            <AppButton type="button" variant="secondary" @click="refetch()">Reintentar</AppButton>
        </div>
        <template v-else-if="document">
            <AppTitle :as="embedded ? 'h2' : 'h1'" size="sm">{{ document.title }}</AppTitle>
            <AppText size="sm">Versión {{ document.version }}</AppText>
            <div class="space-y-2">
                <AppText>{{ data.provider.name }} · {{ data.provider.brand }}</AppText>
                <AppText>RFC: {{ data.provider.rfc }}</AppText>
                <AppText>Domicilio: {{ data.provider.address }}</AppText>
                <AppText>Contacto: {{ data.provider.email }} · {{ data.provider.phone }}</AppText>
                <AppText>Sitio web: {{ data.provider.site_url }}</AppText>
            </div>
            <section v-for="section in document.sections" :key="section.title" class="space-y-2">
                <AppTitle :as="embedded ? 'h3' : 'h2'" size="sm">{{ section.title }}</AppTitle>
                <AppText v-for="paragraph in section.paragraphs" :key="paragraph">{{
                    paragraph
                }}</AppText>
            </section>
        </template>
    </div>
</template>
