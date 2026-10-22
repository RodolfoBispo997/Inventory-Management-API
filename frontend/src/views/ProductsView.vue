<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import {
  ArrowDownToLine,
  ArrowUpFromLine,
  Clock3,
  Package,
  Plus,
  RefreshCw,
  Search,
  Truck,
  X,
} from "@lucide/vue";
import FeedbackMessage from "../components/FeedbackMessage.vue";
import PageHeader from "../components/PageHeader.vue";
import { useFeedback } from "../composables/useFeedback";
import { allPages, request } from "../services/api";
import type {
  Department,
  Product,
  ProductSupplier,
  StockMovement,
  Supplier,
} from "../types/api";

const { busy, error, setError, fieldError } = useFeedback();
const products = ref<Product[]>([]);
const suppliers = ref<Supplier[]>([]);
const departments = ref<Department[]>([]);
const search = ref("");
const modal = ref<"product" | "movement" | "supplier" | null>(null);
const selected = ref<Product | null>(null);
const movements = ref<StockMovement[]>([]);
const prices = ref<ProductSupplier[]>([]);
const historyOpen = ref(false);
const historyLoading = ref(false);
const productName = ref("");
const sku = ref("");
const minimum = ref(0);
const type = ref<"IN" | "OUT">("IN");
const quantity = ref(1);
const departmentId = ref("");
const reason = ref("");
const supplierId = ref("");
const price = ref("");
const supplierCode = ref("");
const notice = ref("");
const filtered = computed(() =>
  products.value.filter((product) =>
    `${product.name} ${product.sku}`
      .toLowerCase()
      .includes(search.value.toLowerCase())
  )
);
const priceSorted = computed(() =>
  [...prices.value].sort((a, b) => Number(a.price) - Number(b.price))
);
async function load() {
  busy.value = true;
  error.value = "";
  try {
    [products.value, suppliers.value, departments.value] = await Promise.all([
      allPages<Product>("/products"),
      allPages<Supplier>("/suppliers"),
      allPages<Department>("/departments"),
    ]);
  } catch (cause) {
    setError(cause);
  } finally {
    busy.value = false;
  }
}
function resetForms() {
  productName.value = "";
  sku.value = "";
  minimum.value = 0;
  quantity.value = 1;
  departmentId.value = "";
  reason.value = "";
  type.value = "IN";
  supplierId.value = "";
  price.value = "";
  supplierCode.value = "";
  notice.value = "";
  error.value = "";
}
async function createProduct() {
  busy.value = true;
  error.value = "";
  try {
    await request("/products", {
      method: "POST",
      body: JSON.stringify({
        name: productName.value,
        sku: sku.value,
        minimum_stock: minimum.value,
      }),
    });
    modal.value = null;
    resetForms();
    await load();
  } catch (cause) {
    setError(cause);
  } finally {
    busy.value = false;
  }
}
async function createMovement() {
  if (!selected.value) return;
  busy.value = true;
  error.value = "";
  const body: Record<string, unknown> = {
    type: type.value,
    quantity: quantity.value,
  };
  if (reason.value) body.reason = reason.value;
  if (type.value === "OUT") body.department_id = Number(departmentId.value);
  try {
    await request(`/products/${selected.value.id}/movements`, {
      method: "POST",
      body: JSON.stringify(body),
    });
    modal.value = null;
    notice.value = "Movimentação registrada. O saldo foi atualizado pela API.";
    await load();
  } catch (cause) {
    setError(cause);
  } finally {
    busy.value = false;
  }
}
async function openHistory(product: Product) {
  selected.value = product;
  historyOpen.value = true;
  historyLoading.value = true;
  error.value = "";
  try {
    const [movementRows, supplierRows] = await Promise.all([
      allPages<StockMovement>(`/products/${product.id}/movements`),
      allPages<ProductSupplier>(`/products/${product.id}/suppliers`),
    ]);
    movements.value = movementRows;
    prices.value = supplierRows;
  } catch (cause) {
    setError(cause);
  } finally {
    historyLoading.value = false;
  }
}
function openDialog(
  kind: "product" | "movement" | "supplier",
  product?: Product
) {
  resetForms();
  selected.value = product ?? null;
  modal.value = kind;
}
async function saveSupplier() {
  if (!selected.value) return;
  busy.value = true;
  error.value = "";
  try {
    await request(
      `/products/${selected.value.id}/suppliers/${supplierId.value}`,
      {
        method: "PUT",
        body: JSON.stringify({
          price: price.value,
          ...(supplierCode.value
            ? { supplier_product_code: supplierCode.value }
            : {}),
        }),
      }
    );
    modal.value = null;
    if (historyOpen.value) await openHistory(selected.value);
    notice.value = "Fornecedor e preço associados ao produto.";
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
      eyebrow="CATÁLOGO E ESTOQUE"
      title="Produtos"
      description="Cadastre produtos, confira níveis e registre movimentações."
      ><template #action
        ><button class="button button-dark" @click="openDialog('product')">
          <Plus :size="17" /> Novo produto
        </button></template
      ></PageHeader
    ><FeedbackMessage
      :message="error || notice"
      :success="Boolean(notice && !error)"
    />
    <section class="panel listing-panel">
      <div class="listing-toolbar">
        <div class="listing-heading">
          <div class="panel-title-line">
            <span class="panel-title-icon icon-lilac"
              ><Package :size="16"
            /></span>
            <h2>Todos os produtos</h2>
            <span class="count-badge">{{ filtered.length }}</span>
          </div>
          <p>Estoque disponível e ponto de reposição</p>
        </div>
        <label class="search-box"
          ><Search :size="16" /><input
            v-model="search"
            placeholder="Buscar nome ou SKU..."
        /></label>
      </div>
      <div v-if="busy && !products.length" class="loading-state">
        <span class="spinner"></span>Carregando produtos da API…
      </div>
      <div v-else-if="filtered.length" class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>PRODUTO</th>
              <th>SKU</th>
              <th>SALDO ATUAL</th>
              <th>ESTOQUE MÍNIMO</th>
              <th>STATUS</th>
              <th>AÇÕES</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in filtered" :key="product.id">
              <td>
                <span class="table-product-icon"><Package :size="15" /></span
                ><strong>{{ product.name }}</strong>
              </td>
              <td class="muted-cell">{{ product.sku }}</td>
              <td>
                <strong>{{ product.current_stock }} un.</strong>
              </td>
              <td class="muted-cell">{{ product.minimum_stock }} un.</td>
              <td>
                <span
                  class="status-pill"
                  :class="
                    product.current_stock <= product.minimum_stock
                      ? 'status-low'
                      : 'status-ok'
                  "
                  >{{
                    product.current_stock <= product.minimum_stock
                      ? "Abaixo do mínimo"
                      : "Em estoque"
                  }}</span
                >
              </td>
              <td>
                <div class="row-actions">
                  <button
                    class="action-button action-in"
                    title="Registrar entrada"
                    @click="openDialog('movement', product)"
                  >
                    <ArrowDownToLine :size="15" /></button
                  ><button
                    class="action-button action-out"
                    title="Registrar saída"
                    @click="
                      openDialog('movement', product);
                      type = 'OUT';
                    "
                  >
                    <ArrowUpFromLine :size="15" /></button
                  ><button
                    class="action-button action-history"
                    title="Ver histórico e fornecedores"
                    @click="openHistory(product)"
                  >
                    <Clock3 :size="15" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!busy" class="empty-state">
        <span class="empty-state-icon"><Package :size="22" /></span
        ><strong>{{
          search
            ? "Nenhum resultado para essa busca"
            : "Nenhum produto cadastrado ainda"
        }}</strong
        ><span>{{
          search
            ? "Tente buscar por outro nome ou SKU."
            : "Adicione produtos para começar a controlar seu estoque."
        }}</span
        ><button
          v-if="!search"
          class="button button-dark button-small"
          @click="openDialog('product')"
        >
          <Plus :size="15" /> Cadastrar produto
        </button>
      </div>
      <div v-else class="loading-state">
        <span class="spinner"></span>Atualizando produtos…
      </div>
      <div v-if="products.length" class="panel-footer">
        <span>O saldo é controlado pela API.</span
        ><button
          class="icon-button"
          :disabled="busy"
          aria-label="Atualizar produtos"
          @click="load"
        >
          <RefreshCw :size="15" />
        </button>
      </div>
    </section>
    <div v-if="modal" class="modal-backdrop" @click.self="modal = null">
      <section class="modal-card">
        <div class="modal-heading">
          <div>
            <p class="eyebrow">
              {{
                modal === "product"
                  ? "CATÁLOGO"
                  : modal === "supplier"
                  ? "FORNECEDORES"
                  : "CONTROLE DE ESTOQUE"
              }}
            </p>
            <h2>
              {{
                modal === "product"
                  ? "Novo produto"
                  : modal === "supplier"
                  ? `Associar fornecedor`
                  : "Registrar movimentação"
              }}
            </h2>
            <p>
              {{
                modal === "product"
                  ? "Os campos com * são obrigatórios."
                  : selected?.name
              }}
            </p>
          </div>
          <button
            class="icon-button"
            aria-label="Fechar janela"
            @click="modal = null"
          >
            <X :size="18" />
          </button>
        </div>
        <FeedbackMessage :message="error" />
        <form
          v-if="modal === 'product'"
          class="modal-form"
          @submit.prevent="createProduct"
        >
          <label class="form-label"
            >Nome do produto *<input
              v-model="productName"
              required
              maxlength="150"
              placeholder="Ex.: Papel sulfite A4" /></label
          ><small v-if="fieldError('name')" class="field-error">{{
            fieldError("name")
          }}</small
          ><label class="form-label"
            >SKU *<input
              v-model="sku"
              required
              maxlength="64"
              placeholder="Ex.: PAP-A4-001" /></label
          ><small v-if="fieldError('sku')" class="field-error">{{
            fieldError("sku")
          }}</small
          ><label class="form-label"
            >Estoque mínimo<input
              v-model.number="minimum"
              type="number"
              min="0"
              step="1" /></label
          ><small class="helper-text"
            >O saldo inicial é definido pelas movimentações de entrada.</small
          >
          <div class="modal-actions">
            <button
              type="button"
              class="button button-outline"
              @click="modal = null"
            >
              Cancelar</button
            ><button type="submit" class="button button-dark" :disabled="busy">
              {{ busy ? "Salvando…" : "Cadastrar produto" }}
            </button>
          </div>
        </form>
        <form
          v-else-if="modal === 'movement'"
          class="modal-form"
          @submit.prevent="createMovement"
        >
          <div class="movement-type-switch">
            <button
              type="button"
              :class="{ selected: type === 'IN' }"
              @click="type = 'IN'"
            >
              <ArrowDownToLine :size="16" /> Entrada <small>IN</small></button
            ><button
              type="button"
              :class="{ selected: type === 'OUT' }"
              @click="type = 'OUT'"
            >
              <ArrowUpFromLine :size="16" /> Saída <small>OUT</small>
            </button>
          </div>
          <label class="form-label"
            >Quantidade *<input
              v-model.number="quantity"
              required
              type="number"
              min="1"
              step="1" /></label
          ><small v-if="fieldError('quantity')" class="field-error">{{
            fieldError("quantity")
          }}</small
          ><label v-if="type === 'OUT'" class="form-label"
            >Setor de destino *<select v-model="departmentId" required>
              <option value="" disabled>Selecione um setor</option>
              <option
                v-for="department in departments"
                :key="department.id"
                :value="department.id"
              >
                {{ department.name }} ({{ department.code }})
              </option></select
            ><small v-if="!departments.length" class="field-error"
              >Cadastre um setor antes de registrar saídas.</small
            ><small v-if="fieldError('department_id')" class="field-error">{{
              fieldError("department_id")
            }}</small></label
          ><label class="form-label"
            >Motivo
            <input
              v-model="reason"
              maxlength="255"
              placeholder="Opcional — ex.: compra ou requisição"
          /></label>
          <div class="modal-actions">
            <button
              type="button"
              class="button button-outline"
              @click="modal = null"
            >
              Cancelar</button
            ><button
              type="submit"
              class="button button-dark"
              :disabled="busy || (type === 'OUT' && !departments.length)"
            >
              {{ busy ? "Registrando…" : "Confirmar movimentação" }}
            </button>
          </div>
        </form>
        <form v-else class="modal-form" @submit.prevent="saveSupplier">
          <label class="form-label"
            >Fornecedor *<select v-model="supplierId" required>
              <option value="" disabled>Selecione um fornecedor</option>
              <option
                v-for="supplier in suppliers"
                :key="supplier.id"
                :value="supplier.id"
              >
                {{ supplier.name }} · {{ supplier.cnpj }}
              </option></select
            ><small v-if="!suppliers.length" class="field-error"
              >Cadastre um fornecedor primeiro.</small
            ></label
          ><label class="form-label"
            >Preço unitário (R$) *<input
              v-model="price"
              required
              type="number"
              min="0"
              max="9999999999.99"
              step="0.01"
              placeholder="0,00" /></label
          ><small v-if="fieldError('price')" class="field-error">{{
            fieldError("price")
          }}</small
          ><label class="form-label"
            >Código do produto no fornecedor<input
              v-model="supplierCode"
              maxlength="100"
              placeholder="Opcional"
          /></label>
          <div class="modal-actions">
            <button
              type="button"
              class="button button-outline"
              @click="modal = null"
            >
              Cancelar</button
            ><button
              type="submit"
              class="button button-dark"
              :disabled="busy || !suppliers.length"
            >
              {{ busy ? "Salvando…" : "Associar fornecedor" }}
            </button>
          </div>
        </form>
      </section>
    </div>
    <div
      v-if="historyOpen && selected"
      class="drawer-backdrop"
      @click.self="historyOpen = false"
    >
      <aside class="history-drawer">
        <div class="drawer-header">
          <div>
            <p class="eyebrow">HISTÓRICO DE ESTOQUE</p>
            <h2>{{ selected.name }}</h2>
            <p>
              {{ selected.sku }} · saldo atual {{ selected.current_stock }} un.
            </p>
          </div>
          <button
            class="icon-button"
            aria-label="Fechar histórico"
            @click="historyOpen = false"
          >
            <X :size="18" />
          </button>
        </div>
        <div class="drawer-actions">
          <button
            class="button button-outline button-small"
            @click="openDialog('supplier', selected)"
          >
            <Truck :size="15" /> Associar fornecedor</button
          ><button
            class="button button-dark button-small"
            @click="
              historyOpen = false;
              openDialog('movement', selected);
            "
          >
            <Plus :size="15" /> Movimento
          </button>
        </div>
        <div class="drawer-section">
          <div class="drawer-section-heading">
            <div class="panel-title-line">
              <span class="panel-title-icon icon-lilac"
                ><Truck :size="15"
              /></span>
              <h3>Fornecedores e preços</h3>
            </div>
            <span class="count-badge">{{ priceSorted.length }}</span>
          </div>
          <div v-if="historyLoading" class="loading-state">
            <span class="spinner"></span>Carregando…
          </div>
          <div v-else-if="priceSorted.length" class="price-list">
            <div
              v-for="supplierPrice in priceSorted"
              :key="supplierPrice.supplier.id"
              class="price-row"
            >
              <div class="supplier-avatar"><Truck :size="15" /></div>
              <div class="supplier-price-info">
                <strong>{{ supplierPrice.supplier.name }}</strong
                ><span
                  >{{ supplierPrice.supplier.cnpj
                  }}{{
                    supplierPrice.supplier_product_code
                      ? ` · ${supplierPrice.supplier_product_code}`
                      : ""
                  }}</span
                >
              </div>
              <strong class="price-amount"
                >R$
                {{
                  Number(supplierPrice.price).toLocaleString("pt-BR", {
                    minimumFractionDigits: 2,
                  })
                }}</strong
              >
            </div>
          </div>
          <div v-else-if="!historyLoading" class="empty-inline">
            Nenhum fornecedor associado a este produto.
          </div>
        </div>
        <div class="drawer-section drawer-history">
          <div class="drawer-section-heading">
            <div class="panel-title-line">
              <span class="panel-title-icon icon-green"
                ><Clock3 :size="15"
              /></span>
              <h3>Movimentações</h3>
            </div>
            <span class="count-badge">{{ movements.length }}</span>
          </div>
          <div v-if="historyLoading" class="loading-state">
            <span class="spinner"></span>Carregando histórico…
          </div>
          <div v-else-if="movements.length" class="timeline">
            <article
              v-for="movement in movements"
              :key="movement.id"
              class="timeline-row"
            >
              <span
                class="timeline-marker"
                :class="movement.type === 'IN' ? 'marker-in' : 'marker-out'"
              >
                <ArrowDownToLine
                  v-if="movement.type === 'IN'"
                  :size="14" /><ArrowUpFromLine v-else :size="14"
              /></span>
              <div class="timeline-copy">
                <strong
                  >{{ movement.type === "IN" ? "Entrada" : "Saída" }} de
                  {{ movement.quantity }} un.</strong
                ><span>{{
                  movement.reason ||
                  (movement.department?.name
                    ? `Setor: ${movement.department.name}`
                    : "Movimentação de estoque")
                }}</span
                ><small>{{
                  new Date(movement.created_at).toLocaleString("pt-BR")
                }}</small>
              </div>
              <span
                class="movement-tag"
                :class="movement.type === 'IN' ? 'tag-in' : 'tag-out'"
                >{{ movement.type }}</span
              >
            </article>
          </div>
          <div v-else-if="!historyLoading" class="empty-inline">
            Nenhuma movimentação registrada para este produto.
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>
