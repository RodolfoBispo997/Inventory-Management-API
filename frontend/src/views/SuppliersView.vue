<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import {
  AtSign,
  Building2,
  CirclePlus,
  Package,
  Phone,
  Plus,
  RefreshCw,
  Search,
  Truck,
  X,
} from "@lucide/vue";
import FeedbackMessage from "../components/FeedbackMessage.vue";
import PageHeader from "../components/PageHeader.vue";
import { useFeedback } from "../composables/useFeedback";
import { isAdmin } from "../composables/useAuthorization";
import { allPages, request } from "../services/api";
import type { Supplier } from "../types/api";

const { busy, error, setError, fieldError } = useFeedback();
const suppliers = ref<Supplier[]>([]);
const search = ref("");
const modal = ref(false);
const name = ref("");
const cnpj = ref("");
const email = ref("");
const phone = ref("");
const notice = ref("");
const filtered = computed(() =>
  suppliers.value.filter((supplier) =>
    `${supplier.name} ${supplier.cnpj} ${supplier.email ?? ""}`
      .toLowerCase()
      .includes(search.value.toLowerCase())
  )
);
async function load() {
  busy.value = true;
  error.value = "";
  try {
    suppliers.value = await allPages<Supplier>("/suppliers");
  } catch (cause) {
    setError(cause);
  } finally {
    busy.value = false;
  }
}
function openModal() {
  if (!isAdmin.value) return;
  name.value = "";
  cnpj.value = "";
  email.value = "";
  phone.value = "";
  error.value = "";
  modal.value = true;
}
async function save() {
  if (!isAdmin.value) return;
  busy.value = true;
  error.value = "";
  try {
    await request("/suppliers", {
      method: "POST",
      body: JSON.stringify({
        name: name.value,
        cnpj: cnpj.value.replace(/\D/g, ""),
        ...(email.value ? { email: email.value } : {}),
        ...(phone.value ? { phone: phone.value } : {}),
      }),
    });
    modal.value = false;
    notice.value = "Fornecedor cadastrado com sucesso.";
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
      eyebrow="CADEIA DE SUPRIMENTOS"
      title="Fornecedores"
      description="Gerencie quem fornece para sua operação e acompanhe os cadastros."
      ><template v-if="isAdmin" #action
        ><button class="button button-dark" @click="openModal">
          <Plus :size="17" /> Novo fornecedor
        </button></template
      ></PageHeader
    ><FeedbackMessage
      :message="error || notice"
      :success="Boolean(notice && !error)"
    />
    <div class="supplier-summary">
      <div class="supplier-summary-icon"><Truck :size="18" /></div>
      <div>
        <strong
          >{{ suppliers.length }} fornecedor{{
            suppliers.length === 1 ? "" : "es"
          }}
          cadastrado{{ suppliers.length === 1 ? "" : "s" }}</strong
        ><span
          >Associações de preços são feitas no histórico de cada produto.</span
        >
      </div>
      <RouterLink to="/products" class="summary-link"
        >Ver produtos <Package :size="14"
      /></RouterLink>
    </div>
    <section class="panel listing-panel">
      <div class="listing-toolbar">
        <div class="listing-heading">
          <div class="panel-title-line">
            <span class="panel-title-icon icon-blue"
              ><Building2 :size="16"
            /></span>
            <h2>Base de fornecedores</h2>
            <span class="count-badge">{{ filtered.length }}</span>
          </div>
          <p>Dados recebidos da API</p>
        </div>
        <label class="search-box"
          ><Search :size="16" /><input
            v-model="search"
            placeholder="Buscar fornecedor..."
        /></label>
      </div>
      <div v-if="busy && !suppliers.length" class="loading-state">
        <span class="spinner"></span>Carregando fornecedores…
      </div>
      <div v-else-if="filtered.length" class="table-wrap">
        <table class="data-table supplier-table">
          <thead>
            <tr>
              <th>FORNECEDOR</th>
              <th>CNPJ</th>
              <th>CONTATO</th>
              <th>TELEFONE</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="supplier in filtered" :key="supplier.id">
              <td>
                <span class="table-product-icon icon-blue"
                  ><Building2 :size="15" /></span
                ><strong>{{ supplier.name }}</strong>
              </td>
              <td class="muted-cell">{{ supplier.cnpj }}</td>
              <td>
                <span v-if="supplier.email" class="contact-cell"
                  ><AtSign :size="14" />{{ supplier.email }}</span
                ><span v-else class="muted-cell">—</span>
              </td>
              <td>
                <span v-if="supplier.phone" class="contact-cell"
                  ><Phone :size="14" />{{ supplier.phone }}</span
                ><span v-else class="muted-cell">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!busy" class="empty-state">
        <span class="empty-state-icon"><Truck :size="22" /></span
        ><strong>{{
          search ? "Nenhum fornecedor encontrado" : "Sua lista começa aqui"
        }}</strong
        ><span>{{
          search
            ? "Tente outra palavra ou CNPJ."
            : isAdmin
              ? "Cadastre fornecedores para associar preços aos produtos."
              : "Nenhum fornecedor foi cadastrado nesta organização ainda."
        }}</span
        ><button
          v-if="!search && isAdmin"
          class="button button-dark button-small"
          @click="openModal"
        >
          <CirclePlus :size="15" /> Cadastrar fornecedor
        </button>
      </div>
      <div v-else class="loading-state">
        <span class="spinner"></span>Atualizando…
      </div>
      <div v-if="suppliers.length" class="panel-footer">
        <span>Os dados são persistidos pela API.</span
        ><button
          class="icon-button"
          aria-label="Atualizar fornecedores"
          :disabled="busy"
          @click="load"
        >
          <RefreshCw :size="15" />
        </button>
      </div>
    </section>
    <div v-if="modal" class="modal-backdrop" @click.self="modal = false">
      <section class="modal-card">
        <div class="modal-heading">
          <div>
            <p class="eyebrow">CADEIA DE SUPRIMENTOS</p>
            <h2>Novo fornecedor</h2>
            <p>Os campos com * são obrigatórios.</p>
          </div>
          <button
            class="icon-button"
            aria-label="Fechar"
            @click="modal = false"
          >
            <X :size="18" />
          </button>
        </div>
        <FeedbackMessage :message="error" />
        <form class="modal-form" @submit.prevent="save">
          <label class="form-label"
            >Nome do fornecedor *<input
              v-model="name"
              required
              maxlength="150"
              placeholder="Ex.: Distribuidora Central" /></label
          ><small v-if="fieldError('name')" class="field-error">{{
            fieldError("name")
          }}</small
          ><label class="form-label"
            >CNPJ *<input
              v-model="cnpj"
              required
              placeholder="Somente números ou com formatação" /></label
          ><small class="helper-text">Informe os 14 dígitos do CNPJ.</small
          ><small v-if="fieldError('cnpj')" class="field-error">{{
            fieldError("cnpj")
          }}</small
          ><label class="form-label"
            >E-mail de contato<input
              v-model="email"
              type="email"
              maxlength="255"
              placeholder="contato@fornecedor.com.br" /></label
          ><small v-if="fieldError('email')" class="field-error">{{
            fieldError("email")
          }}</small
          ><label class="form-label"
            >Telefone<input
              v-model="phone"
              maxlength="30"
              placeholder="(11) 4000-0000"
          /></label>
          <div class="modal-actions">
            <button
              type="button"
              class="button button-outline"
              @click="modal = false"
            >
              Cancelar</button
            ><button type="submit" class="button button-dark" :disabled="busy">
              {{ busy ? "Salvando…" : "Cadastrar fornecedor" }}
            </button>
          </div>
        </form>
      </section>
    </div>
  </div>
</template>
