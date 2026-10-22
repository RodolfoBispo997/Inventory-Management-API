import type { Paginated } from "../types/api";

const baseUrl = (
    import.meta.env.VITE_API_BASE_URL || "http://127.0.0.1:8000/api"
).replace(/\/$/, "");
const tokenKey = "inventory_token";

export class ApiError extends Error {
    status: number;
    errors?: Record<string, string[]>;
    constructor(
        status: number,
        message: string,
        errors?: Record<string, string[]>,
    ) {
        super(message);
        this.name = "ApiError";
        this.status = status;
        this.errors = errors;
    }
}

export function getToken(): string | null {
    return sessionStorage.getItem(tokenKey);
}
export function saveToken(token: string): void {
    sessionStorage.setItem(tokenKey, token);
}
export function clearToken(): void {
    sessionStorage.removeItem(tokenKey);
}

export async function request<T>(
    path: string,
    options: RequestInit = {},
): Promise<T> {
    const headers = new Headers(options.headers);
    headers.set("Accept", "application/json");
    if (options.body) headers.set("Content-Type", "application/json");
    const token = getToken();
    if (token) headers.set("Authorization", `Bearer ${token}`);
    let response: Response;
    try {
        response = await fetch(`${baseUrl}${path}`, { ...options, headers });
    } catch {
        throw new ApiError(
            0,
            "Não foi possível conectar à API. Verifique se o Laravel está em execução e a URL configurada.",
        );
    }
    const payload =
        response.status === 204
            ? null
            : await response.json().catch(() => null);
    if (!response.ok) {
        const message =
            response.status === 401 && token
                ? "Sua sessão expirou. Entre novamente."
                : response.status === 401
                  ? payload?.message || "E-mail ou senha inválidos."
                  : response.status === 404
                    ? "O registro solicitado não foi encontrado."
                    : response.status === 422
                      ? "Confira os campos destacados e tente novamente."
                      : payload?.message ||
                        "Ocorreu um erro ao comunicar com a API.";
        const error = new ApiError(response.status, message, payload?.errors);
        if (response.status === 401 && token) {
            clearToken();
            window.dispatchEvent(new CustomEvent("api:unauthorized"));
        }
        throw error;
    }
    return payload as T;
}

export async function allPages<T>(path: string): Promise<T[]> {
    const results: T[] = [];
    let next: string | null = path;
    while (next) {
        const page: Paginated<T> = await request<Paginated<T>>(
            next.startsWith(baseUrl) ? next.slice(baseUrl.length) : next,
        );
        results.push(...page.data);
        next = page.links?.next ? page.links.next.replace(baseUrl, "") : null;
    }
    return results;
}

export function queryString(
    values: Record<string, string | number | undefined | null>,
): string {
    const params = new URLSearchParams();
    Object.entries(values).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== "")
            params.set(key, String(value));
    });
    return params.toString();
}
