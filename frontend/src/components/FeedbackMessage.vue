<script setup lang="ts">defineProps<{ message: string; success?: boolean }>();</script>
<template><p v-if="message" class="feedback" :class="success ? 'feedback-success' : 'feedback-error'" role="alert">{{ message }}</p></template>
