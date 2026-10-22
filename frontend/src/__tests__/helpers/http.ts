import { vi } from "vitest";

export interface MockHttpResponse {
  status?: number;
  body: unknown;
}

export type MockHttpHandler = (
  url: URL,
  init?: RequestInit,
) => MockHttpResponse | Promise<MockHttpResponse>;

export function mockHttp(handler: MockHttpHandler) {
  const fetchMock = vi.fn(
    async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
      const result = await handler(new URL(String(input)), init);
      const status = result.status ?? 200;

      return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => result.body,
      } as Response;
    },
  );

  vi.stubGlobal("fetch", fetchMock);

  return fetchMock;
}

export function paginated<T>(data: T[]) {
  return {
    data,
    links: { next: null },
    meta: { current_page: 1, last_page: 1, per_page: 15, total: data.length },
  };
}
