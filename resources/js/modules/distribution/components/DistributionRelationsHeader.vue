<script setup lang="ts">
import { PlusIcon } from '@heroicons/vue/24/outline';

import { formatDistributionFrequency } from '@/modules/distribution/schemas/distributionSchemas';
import type { DistributionRule } from '@/modules/distribution/types';
import { AppButton, AppSectionBar } from '@/modules/shared/components';

const props = defineProps<{
    rule: DistributionRule;
}>();

const emit = defineEmits<{
    create: [];
}>();
</script>

<template>
    <AppSectionBar
        as="h1"
        :title="props.rule.name"
        :description="`${formatDistributionFrequency(props.rule.frequency)} · ${props.rule.outcomesCount} relaciones registradas`"
    >
        <template #actions>
            <AppButton variant="primary" aria-label="Crear relación" @click="emit('create')">
                <PlusIcon class="h-4 w-4" />
            </AppButton>
        </template>
    </AppSectionBar>
</template>
