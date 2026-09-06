import { inject, type InjectionKey } from 'vue';
import type { TenantSwitchVisit } from '@/Composables/useTenantSwitchVisit';

export const DashboardNavigationKey: InjectionKey<TenantSwitchVisit> = Symbol('dashboard-navigation');

export function useDashboardNavigation(): TenantSwitchVisit {
    const navigation = inject(DashboardNavigationKey);
    if (!navigation) {
        throw new Error('DashboardNavigation not provided — must be used within Pages/Dashboard.vue');
    }
    return navigation;
}
