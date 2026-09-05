declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: {
            flash: {
                success?: string | null;
                error?: string | null;
                info?: string | null;
                status?: string | null;
            };
            auth: {
                user: {
                    id: string | number;
                    name: string;
                    email: string;
                } | null;
            };
            can: {
                viewUsers?: boolean;
                viewRoles?: boolean;
                viewAuditLogs?: boolean;
                viewMedia?: boolean;
                [key: string]: boolean | undefined;
            };
            locale: string;
            languages: { value: string; label: string; flag?: string }[];
            navigation: App.Data.NavigationItemData[];
        };
    }
}
