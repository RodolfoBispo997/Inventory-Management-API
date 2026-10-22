import { flushPromises, mount, RouterLinkStub, type VueWrapper } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import InvitationsView from "../views/InvitationsView.vue";
import { isAdmin, resetAuthorization } from "../composables/useAuthorization";
import { clearToken, saveToken } from "../services/api";
import { mockHttp, paginated } from "./helpers/http";

const wrappers: VueWrapper[] = [];
const invitation = {
  id: 19,
  email: "operator@example.test",
  invited_by: { id: 1, name: "Admin" },
  expires_at: "2030-06-01T12:00:00.000000Z",
  accepted_at: null,
  created_at: "2030-05-25T12:00:00.000000Z",
};

beforeEach(() => {
  clearToken();
  saveToken("admin-token");
  resetAuthorization();
  isAdmin.value = true;
});

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
  vi.unstubAllGlobals();
  clearToken();
  resetAuthorization();
});

describe("InvitationsView", () => {
  it("lista convites e envia somente o e-mail ao endpoint existente", async () => {
    const fetchMock = mockHttp((url, init) => {
      if (url.pathname.endsWith("/organization-invitations") && init?.method === "POST") {
        return { status: 201, body: { data: invitation } };
      }

      if (url.pathname.endsWith("/organization-invitations")) {
        return { body: paginated([invitation]) };
      }

      return { status: 404, body: { message: "Not found" } };
    });

    const wrapper = mount(InvitationsView, {
      global: { stubs: { RouterLink: RouterLinkStub } },
    });
    wrappers.push(wrapper);
    await flushPromises();

    expect(wrapper.text()).toContain("operator@example.test");
    expect(wrapper.text()).toContain("Pendente");

    await wrapper.get('input[type="email"]').setValue("new-operator@example.test");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    const postCall = fetchMock.mock.calls.find(([, init]) => init?.method === "POST");
    expect(postCall?.[0]).toContain("/api/organization-invitations");
    expect(JSON.parse(String(postCall?.[1]?.body))).toEqual({
      email: "new-operator@example.test",
    });
    expect(wrapper.text()).toContain("Convite enviado para new-operator@example.test");
    expect(wrapper.text()).not.toContain("a".repeat(64));
  });
});
