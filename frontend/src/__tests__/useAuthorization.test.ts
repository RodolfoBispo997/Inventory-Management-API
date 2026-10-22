import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  checkAdminPermission,
  isAdmin,
  resetAuthorization,
} from "../composables/useAuthorization";
import { clearToken, saveToken } from "../services/api";
import { mockHttp, paginated } from "./helpers/http";

describe("useAuthorization", () => {
  beforeEach(() => {
    clearToken();
    resetAuthorization();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    clearToken();
    resetAuthorization();
  });

  it("classifica como admin quando a listagem de convites responde 200", async () => {
    saveToken("admin-token");
    const fetchMock = mockHttp(() => ({ body: paginated([]) }));

    await expect(checkAdminPermission()).resolves.toBe(true);

    expect(isAdmin.value).toBe(true);
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock.mock.calls[0]?.[0]).toContain(
      "/api/organization-invitations?page=1",
    );
  });

  it("classifica como operator quando a listagem responde 403", async () => {
    saveToken("operator-token");
    mockHttp(() => ({ status: 403, body: { message: "Forbidden" } }));

    await expect(checkAdminPermission()).resolves.toBe(false);

    expect(isAdmin.value).toBe(false);
  });

  it("não classifica usuário sem token como operator nem chama a API", async () => {
    const fetchMock = mockHttp(() => ({ body: paginated([]) }));

    await expect(checkAdminPermission()).resolves.toBe(false);

    expect(isAdmin.value).toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("mantém o papel indeterminado quando ocorre falha de rede", async () => {
    saveToken("admin-token");
    mockHttp(() => {
      throw new TypeError("network failure");
    });

    await expect(checkAdminPermission()).rejects.toMatchObject({ status: 0 });

    expect(isAdmin.value).toBeNull();
  });

  it("mantém o papel indeterminado quando a API responde erro 500", async () => {
    saveToken("admin-token");
    mockHttp(() => ({ status: 500, body: { message: "Server error" } }));

    await expect(checkAdminPermission()).rejects.toMatchObject({ status: 500 });

    expect(isAdmin.value).toBeNull();
  });
});
