export interface AuthUser {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    locale: string;
    is_active: boolean;
}

export interface SupportedLanguageOption {
    code: string;
    label: string;
    flag: string;
}

export interface FlashMessages {
    success: string | null;
    error: string | null;
    info: string | null;
    status: string | null;
}

export interface SharedProps {
    app: { name: string };
    auth: { user: AuthUser | null };
    tenant: { active: App.Data.Tenants.TenantListItemData | null; available: App.Data.Tenants.TenantListItemData[] };
    can: Record<string, boolean>;
    flash: FlashMessages;
    translations: Record<string, string>;
    locale: string;
    languages: SupportedLanguageOption[];
    canResetPassword: boolean;
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}
