import { createRouter, createWebHistory } from "vue-router";
import { ApiError, getToken } from "../services/api";
import { checkAdminPermission } from "../composables/useAuthorization";
import LoginView from "../views/LoginView.vue";
import DashboardView from "../views/DashboardView.vue";
import ProductsView from "../views/ProductsView.vue";
import SuppliersView from "../views/SuppliersView.vue";
import DepartmentsView from "../views/DepartmentsView.vue";
import ReportsView from "../views/ReportsView.vue";
import InvitationsView from "../views/InvitationsView.vue";
import AcceptInvitationView from "../views/AcceptInvitationView.vue";

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: "/login",
            name: "login",
            component: LoginView,
            meta: { guest: true },
        },
        {
            path: "/accept-invitation",
            name: "accept-invitation",
            component: AcceptInvitationView,
            meta: { public: true },
        },
        {
            path: "/",
            name: "dashboard",
            component: DashboardView,
            meta: { auth: true },
        },
        {
            path: "/products",
            name: "products",
            component: ProductsView,
            meta: { auth: true },
        },
        {
            path: "/suppliers",
            name: "suppliers",
            component: SuppliersView,
            meta: { auth: true },
        },
        {
            path: "/departments",
            name: "departments",
            component: DepartmentsView,
            meta: { auth: true },
        },
        {
            path: "/reports",
            name: "reports",
            component: ReportsView,
            meta: { auth: true },
        },
        {
            path: "/invitations",
            name: "invitations",
            component: InvitationsView,
            meta: { auth: true, admin: true },
        },
        { path: "/:pathMatch(.*)*", redirect: "/" },
    ],
});

router.beforeEach(async (to) => {
    if (to.meta.auth && !getToken()) return { name: "login" };
    if (to.meta.guest && getToken()) return { name: "dashboard" };

    if (to.meta.admin) {
        try {
            if (!(await checkAdminPermission())) return { name: "dashboard" };
        } catch (error: unknown) {
            if (error instanceof ApiError && error.status === 401) {
                return { name: "login" };
            }

            return { name: "dashboard" };
        }
    }
});

export default router;
