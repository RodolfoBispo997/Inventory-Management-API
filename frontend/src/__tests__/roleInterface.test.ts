import { flushPromises, mount, RouterLinkStub, type VueWrapper } from "@vue/test-utils";
import type { Component } from "vue";
import { createMemoryHistory, createRouter } from "vue-router";
import { afterEach, describe, expect, it, vi } from "vitest";
import AppLayout from "../layouts/AppLayout.vue";
import DepartmentsView from "../views/DepartmentsView.vue";
import ProductsView from "../views/ProductsView.vue";
import SuppliersView from "../views/SuppliersView.vue";
import { isAdmin, resetAuthorization } from "../composables/useAuthorization";
import { clearToken, saveToken } from "../services/api";
import { mockHttp, paginated } from "./helpers/http";

const wrappers: VueWrapper[] = [];

function track<T extends VueWrapper>(wrapper: T): T {
  wrappers.push(wrapper);
  return wrapper;
}

function inventoryResponse(url: URL) {
  if (url.pathname.endsWith("/products")) {
    return paginated([
      {
        id: 7,
        name: "Papel A4",
        sku: "PAP-A4",
        minimum_stock: 2,
        current_stock: 8,
      },
    ]);
  }

  return paginated([]);
}

function mountPage(component: Component) {
  return track(
    mount(component, {
      global: { stubs: { RouterLink: RouterLinkStub } },
    }),
  );
}

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
  vi.unstubAllGlobals();
  clearToken();
  resetAuthorization();
});

describe("interface por papel", () => {
  it("mostra o link de convites para admin e o oculta para operator", async () => {
    saveToken("admin-token");
    mockHttp((url) => {
      if (url.pathname.endsWith("/auth/me")) {
        return { body: { data: { id: 1, name: "Admin", email: "admin@example.test" } } };
      }

      return { body: paginated([]) };
    });
    isAdmin.value = null;
    const adminRouter = createProtectedRouter();
    const adminWrapper = track(await mountLayout(adminRouter));
    await flushPromises();

    expect(adminWrapper.findAll("a").some((link) => link.text() === "Convites")).toBe(true);

    adminWrapper.unmount();
    wrappers.splice(wrappers.indexOf(adminWrapper), 1);

    saveToken("operator-token");
    resetAuthorization();
    mockHttp((url) => {
      if (url.pathname.endsWith("/auth/me")) {
        return { body: { data: { id: 2, name: "Operator", email: "operator@example.test" } } };
      }

      return { status: 403, body: { message: "Forbidden" } };
    });
    const operatorWrapper = track(await mountLayout(createProtectedRouter()));
    await flushPromises();

    expect(operatorWrapper.findAll("a").some((link) => link.text() === "Convites")).toBe(false);
  });

  it("oculta criação de produto e associação de preço para operator, mantendo movimentações", async () => {
    mockHttp((url) => ({ body: inventoryResponse(url) }));
    isAdmin.value = true;
    const adminWrapper = mountPage(ProductsView);
    await flushPromises();

    expect(adminWrapper.findAll("button").some((button) => button.text().includes("Novo produto"))).toBe(true);
    await adminWrapper.get('button[title="Ver histórico e fornecedores"]').trigger("click");
    await flushPromises();
    expect(adminWrapper.text()).toContain("Associar fornecedor");
    adminWrapper.unmount();

    isAdmin.value = false;
    const operatorWrapper = mountPage(ProductsView);
    await flushPromises();

    expect(operatorWrapper.findAll("button").some((button) => button.text().includes("Novo produto"))).toBe(false);
    expect(operatorWrapper.find('[title="Registrar entrada"]').exists()).toBe(true);
    expect(operatorWrapper.find('[title="Registrar saída"]').exists()).toBe(true);
    await operatorWrapper.get('button[title="Ver histórico e fornecedores"]').trigger("click");
    await flushPromises();
    expect(operatorWrapper.text()).not.toContain("Associar fornecedor");
  });

  it("oculta cadastro de fornecedores e setores para operator", async () => {
    mockHttp((url) => ({ body: inventoryResponse(url) }));
    isAdmin.value = true;
    const adminSuppliers = mountPage(SuppliersView);
    const adminDepartments = mountPage(DepartmentsView);
    await flushPromises();
    expect(adminSuppliers.text()).toContain("Novo fornecedor");
    expect(adminDepartments.text()).toContain("Novo setor");
    adminSuppliers.unmount();
    adminDepartments.unmount();

    isAdmin.value = false;
    const operatorSuppliers = mountPage(SuppliersView);
    const operatorDepartments = mountPage(DepartmentsView);
    await flushPromises();
    expect(operatorSuppliers.text()).not.toContain("Novo fornecedor");
    expect(operatorDepartments.text()).not.toContain("Novo setor");
  });
});

function createProtectedRouter() {
  const placeholder = { template: "<div />" };

  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/login", component: placeholder, meta: { guest: true } },
      { path: "/", component: placeholder, meta: { auth: true } },
      {
        path: "/products",
        component: placeholder,
        meta: { auth: true },
      },
      { path: "/suppliers", component: placeholder, meta: { auth: true } },
      { path: "/departments", component: placeholder, meta: { auth: true } },
      { path: "/reports", component: placeholder, meta: { auth: true } },
      { path: "/invitations", component: placeholder, meta: { auth: true } },
    ],
  });
}

async function mountLayout(router: ReturnType<typeof createProtectedRouter>) {
  await router.push("/products");
  await router.isReady();

  return mount(AppLayout, {
    global: { plugins: [router] },
    slots: { default: "Conteúdo" },
  });
}
