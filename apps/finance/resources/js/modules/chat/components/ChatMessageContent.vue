<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{ content: string }>();
const lines = computed(() =>
    props.content.split('\n').map((line) => ({
        bullet: /^[-*] /.test(line),
        parts: line
            .replace(/^[-*] /, '')
            .split(/(\*\*[^*]+\*\*)/g)
            .map((text) => ({
                bold: text.startsWith('**') && text.endsWith('**'),
                text: text.startsWith('**') && text.endsWith('**') ? text.slice(2, -2) : text,
            })),
    })),
);
</script>
<template>
    <div class="space-y-1 break-words">
        <p v-for="(line, index) in lines" :key="index" class="min-h-5 whitespace-pre-wrap">
            <span v-if="line.bullet" aria-hidden="true">• </span
            ><template v-for="(part, position) in line.parts" :key="position"
                ><strong v-if="part.bold">{{ part.text }}</strong
                ><span v-else>{{ part.text }}</span></template
            >
        </p>
    </div>
</template>
