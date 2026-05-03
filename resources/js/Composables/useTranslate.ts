import { computed } from 'vue';
import { usePageProps } from '@/Composables/usePageProps';

export function useTranslate() {
    const props = usePageProps();
    const translations = computed<Record<string, string>>(() => props.translations ?? {});

    const t = (key: string): string => translations.value[key] ?? key;

    return { t };
}
