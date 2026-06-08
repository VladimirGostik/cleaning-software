import { computed } from 'vue';
import { usePageProps } from '@/Composables/usePageProps';

/**
 * useLocalizedDate — returns a formatDate helper that formats dates
 * using the active locale from Inertia shared props.
 */
export function useLocalizedDate() {
    const pageProps = usePageProps();

    const localeTag = computed(() => {
        const map: Record<string, string> = { sk: 'sk-SK', en: 'en-GB', uk: 'uk-UA' };
        return map[pageProps.locale] ?? 'sk-SK';
    });

    function formatDate(dateStr: string): string {
        return new Date(dateStr).toLocaleDateString(localeTag.value, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

    return { formatDate };
}
