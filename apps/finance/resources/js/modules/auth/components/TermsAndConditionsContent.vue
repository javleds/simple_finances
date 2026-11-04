<script setup lang="ts">
import LegalDocumentContent from './LegalDocumentContent.vue';
withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false });
</script>
<template><LegalDocumentContent kind="terms" :embedded="embedded" /></template>
