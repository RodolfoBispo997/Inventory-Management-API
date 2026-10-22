<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Mail, RefreshCw, Send, UserRoundPlus } from "@lucide/vue";
import FeedbackMessage from "../components/FeedbackMessage.vue";
import PageHeader from "../components/PageHeader.vue";
import { useFeedback } from "../composables/useFeedback";
import {
  checkAdminPermission,
  isAdmin,
} from "../composables/useAuthorization";
import { allPages, request } from "../services/api";
import type { OrganizationInvitation } from "../types/api";

const { busy, error, setError, fieldError } = useFeedback();
const invitations = ref<OrganizationInvitation[]>([]);
const loading = ref(false);
const email = ref("");
const notice = ref("");
const pendingCount = computed(
  () => invitations.value.filter((invitation) => getStatus(invitation) === "Pendente").length,
);

function getStatus(invitation: OrganizationInvitation): string {
  if (invitation.accepted_at) {
    return "Aceito";
  }

  return new Date(invitation.expires_at).getTime() < Date.now()
    ? "Expirado"
    : "Pendente";
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
  }).format(new Date(value));
}

async function load(): Promise<void> {
  loading.value = true;
  error.value = "";

  try {
    if (!(await checkAdminPermission())) {
      error.value = "Somente administradores podem consultar convites.";
      return;
    }

    invitations.value = await allPages<OrganizationInvitation>(
      "/organization-invitations",
    );
  } catch (cause) {
    setError(cause);
  } finally {
    loading.value = false;
  }
}

async function createInvitation(): Promise<void> {
  if (!isAdmin.value) {
    return;
  }

  busy.value = true;
  error.value = "";
  notice.value = "";

  try {
    await request<{ data: OrganizationInvitation }>(
      "/organization-invitations",
      {
        method: "POST",
        body: JSON.stringify({ email: email.value }),
      },
    );

    notice.value = `Convite enviado para ${email.value}. O token foi enviado por e-mail e não fica visível nesta tela.`;
    email.value = "";
    await load();
  } catch (cause) {
    setError(cause);
  } finally {
    busy.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="page-enter">
    <PageHeader
      eyebrow="ACESSO À ORGANIZAÇÃO"
      title="Convites"
      description="Convide operadores para consultar o estoque e registrar movimentações."
    >
      <template #action>
        <span class="count-badge">{{ pendingCount }} pendente{{ pendingCount === 1 ? "" : "s" }}</span>
      </template>
    </PageHeader>

    <FeedbackMessage :message="error || notice" :success="Boolean(notice && !error)" />

    <div v-if="isAdmin === null && !error" class="loading-state">
      <span class="spinner"></span>Verificando permissões…
    </div>

    <template v-else-if="isAdmin">
      <section class="panel" style="padding: 18px; margin-bottom: 16px">
        <div class="panel-title-line" style="margin-bottom: 14px">
          <span class="panel-title-icon icon-green"><UserRoundPlus :size="16" /></span>
          <h2>Convidar operador</h2>
        </div>
        <form class="filter-strip" @submit.prevent="createInvitation">
          <label class="form-label" style="flex: 1; min-width: 220px">
            E-mail do operador
            <input
              v-model="email"
              required
              type="email"
              maxlength="255"
              autocomplete="email"
              placeholder="operador@empresa.com"
            />
          </label>
          <button class="button button-dark" type="submit" :disabled="busy">
            <Send :size="15" />{{ busy ? "Enviando…" : "Enviar convite" }}
          </button>
        </form>
        <small v-if="fieldError('email')" class="field-error">{{ fieldError("email") }}</small>
        <p class="helper-text">
          O token seguirá pelo e-mail configurado. O convidado deve abrir a tela
          pública <code>/accept-invitation</code> no frontend e informar o token
          recebido.
        </p>
      </section>

      <section class="panel listing-panel">
        <div class="listing-toolbar">
          <div class="listing-heading">
            <div class="panel-title-line">
              <span class="panel-title-icon icon-blue"><Mail :size="16" /></span>
              <h2>Convites da organização</h2>
              <span class="count-badge">{{ invitations.length }}</span>
            </div>
            <p>O token não é retornado pela API nem exibido aqui.</p>
          </div>
          <button
            class="icon-button"
            aria-label="Atualizar convites"
            :disabled="loading"
            @click="load"
          >
            <RefreshCw :size="15" />
          </button>
        </div>

        <div v-if="loading && !invitations.length" class="loading-state">
          <span class="spinner"></span>Carregando convites…
        </div>
        <div v-else-if="invitations.length" class="table-wrap">
          <table class="data-table supplier-table">
            <thead>
              <tr>
                <th>E-MAIL</th>
                <th>CRIADO POR</th>
                <th>EXPIRA EM</th>
                <th>STATUS</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="invitation in invitations" :key="invitation.id">
                <td><strong>{{ invitation.email }}</strong></td>
                <td class="muted-cell">{{ invitation.invited_by?.name ?? "—" }}</td>
                <td class="muted-cell">{{ formatDate(invitation.expires_at) }}</td>
                <td>
                  <span
                    class="status-pill"
                    :class="getStatus(invitation) === 'Pendente' ? 'status-ok' : getStatus(invitation) === 'Aceito' ? 'status-low' : 'status-low'"
                  >{{ getStatus(invitation) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else-if="!loading" class="empty-state">
          <span class="empty-state-icon"><Mail :size="22" /></span>
          <strong>Nenhum convite criado</strong>
          <span>Os convites enviados pela organização aparecerão aqui.</span>
        </div>
        <div v-else class="loading-state">
          <span class="spinner"></span>Atualizando convites…
        </div>
      </section>
    </template>
  </div>
</template>
