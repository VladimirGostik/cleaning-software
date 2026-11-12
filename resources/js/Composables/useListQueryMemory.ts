import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { usePageProps } from '@/Composables/usePageProps';
import { clearAll, restore, save } from '@/utils/listQueryMemory';
import type { SharedProps } from '@/types';

const version = ref(0);

function pathOf(href: string): string {
    return href.split(/[?#]/)[0] ?? href;
}

function listPathsOf(navigation: App.Data.NavigationItemData[]): string[] {
    const paths = navigation
        .flatMap((item) => [item.href, ...item.children.map((child) => child.href)])
        .filter((href) => href.startsWith('/'))
        .map(pathOf)
        .filter((path) => path !== '/');
    return [...new Set(paths)];
}

export function useRememberedQuery() {
    const page = usePage();
    const props = usePageProps();
    const listPaths = computed(() => listPathsOf(props.value.navigation ?? []));

    function withRememberedQuery(href: string): string {
        void page.url;
        void version.value;
        const tenantId = props.value.tenant?.active?.id;
        return tenantId ? restore(tenantId, href, listPaths.value) : href;
    }

    return { withRememberedQuery, listPaths };
}

export function useListQueryMemory() {
    const page = usePage();
    const { withRememberedQuery, listPaths } = useRememberedQuery();
    let removeNavigate: (() => void) | undefined;

    function remember(url: string, tenantId: string | undefined): void {
        if (!tenantId) return;
        save(tenantId, url, listPaths.value);
        version.value++;
    }

    onMounted(() => {
        remember(page.url, (page.props as SharedProps).tenant?.active?.id);
        removeNavigate = router.on('navigate', (event: { detail: { page: ReturnType<typeof usePage> } }) => {
            const next = event.detail.page;
            remember(next.url, (next.props as unknown as SharedProps).tenant?.active?.id);
        });
    });

    onUnmounted(() => removeNavigate?.());

    function clear(): void {
        clearAll();
        version.value++;
    }

    return { withRememberedQuery, clear };
}
