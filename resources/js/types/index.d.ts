export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
}

export interface SelectOption {
    id: string | number;
    name: string;
}

export interface StatusOption {
    id: string | number;
    name: string;
    color?: string;
    icon?: string;
}

export interface Breadcrumb {
    label: string;
    url?: string;
}

export interface TableColumn {
    key: string;
    label: string;
    sortable?: boolean;
    class?: string;
}

export interface RoleOption {
    id: string | number;
    name: string;
}

export interface UserOption {
    id: string | number;
    name: string;
    email: string;
}

export interface SharedFlash {
    success?: string | null;
    error?: string | null;
    info?: string | null;
    status?: string | null;
}

export interface SharedUser {
    id: string;
    name: string;
    email: string;
    locale: App.Enums.SupportedLanguage;
}

export interface SharedLanguage {
    value: App.Enums.SupportedLanguage;
    label: string;
    flag?: string;
}

export interface SharedTenant {
    active: App.Data.Tenants.TenantListItemData | null;
    available: App.Data.Tenants.TenantListItemData[];
}

export interface TenantColorOption {
    value: App.Enums.TenantColorEnum;
    label: string;
}

export interface SharedProps {
    flash: SharedFlash;
    auth: { user: SharedUser | null };
    can: Record<string, boolean>;
    locale: string;
    languages: SharedLanguage[];
    navigation: App.Data.NavigationItemData[];
    tenant: SharedTenant;
    tenantColors: TenantColorOption[];
}
