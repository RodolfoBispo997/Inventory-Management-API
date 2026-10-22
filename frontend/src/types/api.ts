export interface User {
    id: number;
    name: string;
    email: string;
}
export interface Product {
    id: number;
    name: string;
    sku: string;
    minimum_stock: number;
    current_stock: number;
}
export interface Supplier {
    id: number;
    name: string;
    cnpj: string;
    email: string | null;
    phone: string | null;
}
export interface Department {
    id: number;
    name: string;
    code: string;
}
export interface StockMovement {
    id: number;
    product_id: number;
    department_id: number | null;
    department?: Department | null;
    type: "IN" | "OUT";
    quantity: number;
    reason: string | null;
    created_at: string;
}
export interface ProductSupplier {
    supplier: Pick<Supplier, "id" | "name" | "cnpj">;
    price: string;
    supplier_product_code: string | null;
}
export interface ReportProduct {
    id: number;
    name: string;
    sku: string;
    consumed_quantity: number;
    movement_count: number;
}
export interface ReportDepartment {
    id: number;
    name: string;
    code: string;
    consumed_quantity: number;
    movement_count: number;
    products: ReportProduct[];
}
export interface ConsumptionReport {
    from: string;
    to: string;
    department_id: number | null;
    departments: ReportDepartment[];
}
export interface Paginated<T> {
    data: T[];
    links?: Record<string, string | null>;
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}
