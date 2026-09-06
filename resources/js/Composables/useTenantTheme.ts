import { computed, type ComputedRef } from 'vue';
import { usePageProps } from '@/Composables/usePageProps';

export function useTenantTheme(): { themeStyle: ComputedRef<Record<'--color-primary', string> | undefined> } {
    const props = usePageProps();

    const themeStyle = computed<Record<'--color-primary', string> | undefined>(() => {
        const color = props.value.tenant.active?.color;
        return color ? { '--color-primary': color } : undefined;
    });

    return { themeStyle };
}
