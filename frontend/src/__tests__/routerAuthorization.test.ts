import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import router from "../router";
import { isAdmin, resetAuthorization } from "../composables/useAuthorization";
import { clearToken, saveToken } from "../services/api";
import { mockHttp, paginated } from "./helpers/http";

beforeEach(async () => {
  clearToken();
  resetAuthorization();
  await router.push("/login");
});

afterEach(() => {
  vi.unstubAllGlobals();
  clearToken();
  resetAuthorization();
});

describe("guard da rota de convites", () => {
  it("permite admin somente depois da confirmação da API", async () => {
    saveToken("admin-token");
    const fetchMock = mockHttp(() => ({ body: paginated([]) }));

    await router.push("/invitations");

    expect(router.currentRoute.value.name).toBe("invitations");
    expect(isAdmin.value).toBe(true);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("redireciona operator ao dashboard após resposta 403", async () => {
    saveToken("operator-token");
    mockHttp(() => ({ status: 403, body: { message: "Forbidden" } }));

    await router.push("/invitations");

    expect(router.currentRoute.value.name).toBe("dashboard");
    expect(isAdmin.value).toBe(false);
  });

  it("redireciona usuário sem token ao login sem consultar a API", async () => {
    const fetchMock = mockHttp(() => ({ body: paginated([]) }));

    await router.push("/invitations");

    expect(router.currentRoute.value.name).toBe("login");
    expect(isAdmin.value).toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("não classifica falha de rede como operator nem exibe convites", async () => {
    saveToken("admin-token");
    mockHttp(() => {
      throw new TypeError("network failure");
    });

    await router.push("/invitations");

    expect(router.currentRoute.value.name).toBe("dashboard");
    expect(isAdmin.value).toBeNull();
  });

  it("não classifica erro 500 como operator nem exibe convites", async () => {
    saveToken("admin-token");
    mockHttp(() => ({ status: 500, body: { message: "Server error" } }));

    await router.push("/invitations");

    expect(router.currentRoute.value.name).toBe("dashboard");
    expect(isAdmin.value).toBeNull();
  });

  it("redireciona para login quando a sessão expira durante a verificação", async () => {
    saveToken("expired-token");
    mockHttp(() => ({ status: 401, body: { message: "Unauthenticated" } }));

    await router.push("/invitations");

    expect(router.currentRoute.value.name).toBe("login");
    expect(isAdmin.value).toBeNull();
  });

  it("mantém a rota atual enquanto a permissão está sendo verificada", async () => {
    saveToken("admin-token");
    let resolveResponse!: (response: { body: unknown }) => void;
    const fetchMock = mockHttp(
      () => new Promise((resolve) => (resolveResponse = resolve)),
    );

    const navigation = router.push("/invitations");
    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());

    expect(router.currentRoute.value.name).toBe("login");

    resolveResponse({ body: paginated([]) });
    await navigation;

    expect(router.currentRoute.value.name).toBe("invitations");
  });
});
