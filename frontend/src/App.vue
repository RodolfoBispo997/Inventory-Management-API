<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from './layouts/AppLayout.vue'

const router = useRouter()
function redirectOnUnauthorized() { router.replace({ name: 'login' }) }
onMounted(() => window.addEventListener('api:unauthorized', redirectOnUnauthorized))
onUnmounted(() => window.removeEventListener('api:unauthorized', redirectOnUnauthorized))
</script>

<template><AppLayout><RouterView /></AppLayout></template>
