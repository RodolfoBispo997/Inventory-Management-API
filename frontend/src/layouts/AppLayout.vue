<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  Boxes,
  ChartNoAxesCombined,
  LayoutDashboard,
  LogOut,
  Menu,
  Package,
  Truck,
  UserRoundPlus,
  Warehouse,
  X,
} from "@lucide/vue";
import { clearToken, getToken, request } from "../services/api";
import { checkAdminPermission, isAdmin, resetAuthorization } from "../composables/useAuthorization";
import type { User } from "../types/api";

const route = useRoute();
const router = useRouter();
const menuOpen = ref(false);
const user = ref<User | null>(null);
const authenticated = computed(
  () => route.meta.auth === true && Boolean(getToken())
);
const links = [
  { label: "Visão geral", to: "/", icon: LayoutDashboard },
  { label: "Produtos", to: "/products", icon: Package },
  { label: "Fornecedores", to: "/suppliers", icon: Truck },
  { label: "Setores", to: "/departments", icon: Warehouse },
  { label: "Relatórios", to: "/reports", icon: ChartNoAxesCombined },
  { label: "Convites", to: "/invitations", icon: UserRoundPlus, admin: true },
];
const visibleLinks = computed(() => links.filter((link) => !link.admin || isAdmin.value));
const roleLabel = computed(() => isAdmin.value === null ? "Verificando acesso…" : isAdmin.value ? "Administrador" : "Operador");
const pageTitle = computed(
  () =>
    visibleLinks.value.find((link) => link.to === route.path)?.label ?? "Gestão de estoque"
);
let loadedToken: string | null = null;
watch(
  () => route.meta.auth,
  (requiresAuth) => {
    const token = getToken();
    if (!token) {
      user.value = null;
      loadedToken = null;
      resetAuthorization();
      return;
    }

    if (!requiresAuth || loadedToken === token) {
      return;
    }

    loadedToken = token;
    user.value = null;
    request<{ data: User }>("/auth/me")
      .then((result) => {
        user.value = result.data;
      })
      .catch(() => undefined);
    checkAdminPermission().catch(() => undefined);
  },
  { immediate: true },
);
async function logout() {
  try {
    await request("/auth/logout", { method: "POST" });
  } catch {
    /* A sessão é encerrada localmente mesmo sem resposta. */
  } finally {
    clearToken();
    user.value = null;
    loadedToken = null;
    resetAuthorization();
    await router.replace({ name: "login" });
  }
}
</script>

<template>
  <RouterView v-if="route.meta.guest || route.meta.public" />
  <div v-else-if="authenticated" class="app-shell">
    <div v-if="menuOpen" class="mobile-scrim" @click="menuOpen = false"></div>
    <aside class="sidebar" :class="{ 'sidebar-open': menuOpen }">
      <div class="brand">
        <span class="brand-mark"><Boxes :size="20" /></span
        ><span class="brand-name">armazém<span>.</span></span
        ><button
          class="icon-button mobile-close"
          aria-label="Fechar menu"
          @click="menuOpen = false"
        >
          <X :size="19" />
        </button>
      </div>
      <p class="side-label">WORKSPACE</p>
      <nav class="nav-list">
        <RouterLink
          v-for="link in visibleLinks"
          :key="link.to"
          :to="link.to"
          class="nav-link"
          :class="{ 'nav-link-active': route.path === link.to }"
          @click="menuOpen = false"
          ><component :is="link.icon" :size="18" stroke-width="1.8" /><span>{{
            link.label
          }}</span
          ><span v-if="link.to === '/products'" class="nav-dot"></span
        ></RouterLink>
      </nav>
      <div class="side-spacer"></div>
      <div class="sidebar-note">
        <span class="note-icon"><Boxes :size="17" /></span>
        <div>
          <strong>Seu estoque, em dia</strong
          ><span>Operação mais simples.</span>
        </div>
      </div>
      <div class="user-block">
        <span class="avatar">{{
          (user?.name ?? "U").slice(0, 1).toUpperCase()
        }}</span>
        <div class="user-info">
          <strong>{{ user?.name ?? "Conta" }}</strong
          ><span>{{ roleLabel }}</span>
        </div>
        <button
          class="logout-button"
          aria-label="Sair da conta"
          title="Sair"
          @click="logout"
        >
          <LogOut :size="17" />
        </button>
      </div>
    </aside>
    <main class="main-area">
      <header class="topbar">
        <div class="topbar-left">
          <button
            class="icon-button mobile-menu"
            aria-label="Abrir menu"
            @click="menuOpen = true"
          >
            <Menu :size="20" />
          </button>
          <div>
            <span class="breadcrumb"
              >Workspace <span>/</span> <strong>{{ pageTitle }}</strong></span
            >
          </div>
        </div>
        <div class="topbar-right">
          <span class="live-dot"></span><span>API conectada</span
          ><span class="top-avatar">{{
            (user?.name ?? "U").slice(0, 1).toUpperCase()
          }}</span>
        </div>
      </header>
      <div class="page-content"><slot /></div>
    </main>
  </div>
  <RouterView v-else />
</template>
