import { ref } from "vue";
import { ApiError, getToken, request } from "../services/api";
import type { OrganizationInvitation, Paginated } from "../types/api";

export const isAdmin = ref<boolean | null>(null);

let permissionCheck: Promise<boolean> | null = null;

export function resetAuthorization(): void {
  permissionCheck = null;
  isAdmin.value = null;
}

export function checkAdminPermission(): Promise<boolean> {
  if (!getToken()) {
    resetAuthorization();
    return Promise.resolve(false);
  }

  if (isAdmin.value !== null) {
    return Promise.resolve(isAdmin.value);
  }

  if (permissionCheck) {
    return permissionCheck;
  }

  const token = getToken();
  permissionCheck = request<Paginated<OrganizationInvitation>>(
    "/organization-invitations?page=1",
  )
    .then(() => {
      if (getToken() === token) {
        isAdmin.value = true;
      }

      return true;
    })
    .catch((error: unknown) => {
      if (error instanceof ApiError && error.status === 403) {
        if (getToken() === token) {
          isAdmin.value = false;
        }

        return false;
      }

      throw error;
    })
    .finally(() => {
      permissionCheck = null;
    });

  return permissionCheck;
}
