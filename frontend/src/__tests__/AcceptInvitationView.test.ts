import { flushPromises, mount, type VueWrapper } from "@vue/test-utils";
import { createMemoryHistory, createRouter } from "vue-router";
import { afterEach, describe, expect, it, vi } from "vitest";
import AcceptInvitationView from "../views/AcceptInvitationView.vue";
import { clearToken } from "../services/api";
import { mockHttp } from "./helpers/http";

const wrappers: VueWrapper[] = [];
const token = "a".repeat(64);

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
  vi.unstubAllGlobals();
  clearToken();
});

describe("AcceptInvitationView", () => {
  it("preenche o token da query, envia o contrato da API e mostra sucesso", async () => {
    const fetchMock = mockHttp(() => ({
      status: 201,
      body: {
        data: { id: 8, name: "Pessoa convidada", email: "invitee@example.test" },
      },
    }));
    const { router, wrapper } = await mountView(`?token=${token}`);
    wrappers.push(wrapper);

    expect((wrapper.get('input[placeholder="Cole o token de 64 caracteres"]').element as HTMLInputElement).value).toBe(token);
    await flushPromises();
    expect(router.currentRoute.value.query.token).toBeUndefined();
    expect(router.currentRoute.value.path).toBe("/accept-invitation");

    await wrapper.get('input[autocomplete="name"]').setValue("Pessoa convidada");
    await wrapper.get('input[autocomplete="new-password"]').setValue("senha-segura-123");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    const postCall = fetchMock.mock.calls.find(([, init]) => init?.method === "POST");
    expect(postCall?.[0]).toContain("/api/invitations/accept");
    expect(JSON.parse(String(postCall?.[1]?.body))).toEqual({
      token,
      name: "Pessoa convidada",
      password: "senha-segura-123",
    });
    expect(wrapper.text()).toContain("Convite aceito com sucesso");
  });

  it("mostra a resposta de erro da API para um token rejeitado", async () => {
    mockHttp(() => ({
      status: 422,
      body: {
        message: "Confira os campos destacados e tente novamente.",
        errors: { token: ["O token informado é inválido."] },
      },
    }));
    const { wrapper } = await mountView();
    wrappers.push(wrapper);

    await wrapper.get('input[placeholder="Cole o token de 64 caracteres"]').setValue(token);
    await wrapper.get('input[autocomplete="name"]').setValue("Pessoa convidada");
    await wrapper.get('input[autocomplete="new-password"]').setValue("senha-segura-123");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(wrapper.text()).toContain("Confira os campos destacados e tente novamente.");
    expect(wrapper.text()).toContain("O token informado é inválido.");
    expect(wrapper.text()).not.toContain("Convite aceito com sucesso");
  });
});

async function mountView(query = "") {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/accept-invitation", component: AcceptInvitationView },
      { path: "/login", component: { template: "<div />" } },
    ],
  });
  await router.push(`/accept-invitation${query}`);
  await router.isReady();

  return {
    router,
    wrapper: mount(AcceptInvitationView, {
      global: { plugins: [router] },
    }),
  };
}
