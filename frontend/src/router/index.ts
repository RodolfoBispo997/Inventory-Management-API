import { createRouter, createWebHistory } from "vue-router";
import { getToken } from "../services/api";
import LoginView from "../views/LoginView.vue";
import DashboardView from "../views/DashboardView.vue";
import ProductsView from "../views/ProductsView.vue";
import SuppliersView from "../views/SuppliersView.vue";
import DepartmentsView from "../views/DepartmentsView.vue";
import ReportsView from "../views/ReportsView.vue";

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
        { path: "/:pathMatch(.*)*", redirect: "/" },
    ],
});

router.beforeEach((to) => {
    if (to.meta.auth && !getToken()) return { name: "login" };
    if (to.meta.guest && getToken()) return { name: "dashboard" };
});

export default router;
