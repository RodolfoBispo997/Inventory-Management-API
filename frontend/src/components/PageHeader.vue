<script setup lang="ts">
defineProps<{ eyebrow: string; title: string; description: string }>()
defineEmits<{ action: [] }>()
</script>

<template><div class="page-heading"><div><p class="eyebrow">{{ eyebrow }}</p><h1>{{ title }}</h1><p class="page-description">{{ description }}</p></div><slot name="action" /></div></template>
