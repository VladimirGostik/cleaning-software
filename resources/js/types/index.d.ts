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

export interface SharedLanguage {
    value: string;
    label: string;
    flag?: string;
}

export interface SharedUser {
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

export interface SharedCan {
    viewUsers?: boolean;
    viewRoles?: boolean;
    viewAuditLogs?: boolean;
    viewMedia?: boolean;
    [key: string]: boolean | undefined;
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: {
            flash: SharedFlash;
            auth: {
                user: SharedUser | null;
            };
            can: SharedCan;
            locale: string;
            languages: SharedLanguage[];
        };
    }
}
