import { reactive } from 'vue';
import { usePage } from '@inertiajs/vue3';

export type InvoiceSettingsDrawerStatus = 'idle' | 'loading' | 'ready' | 'error';

interface InvoiceSettingsDrawerState {
    isOpen: boolean;
    status: InvoiceSettingsDrawerStatus;
    settings: App.Data.Invoices.InvoiceSettingsData | null;
}

interface InertiaPartialPayload {
    props: {
        settings: App.Data.Invoices.InvoiceSettingsData;
    };
}

function isInertiaPartialPayload(value: unknown): value is InertiaPartialPayload {
    if (typeof value !== 'object' || value === null || !('props' in value)) return false;

    const props = (value as { props: unknown }).props;

    return typeof props === 'object' && props !== null && 'settings' in props;
}

/**
 * Fetches `App.Data.Invoices.InvoiceSettingsData` from the Inertia-rendered settings page as a
 * raw partial (X-Inertia headers) so a `SideDrawer` can host the same form without a page visit.
 */
export function useInvoiceSettingsDrawer() {
    const state = reactive<InvoiceSettingsDrawerState>({
        isOpen: false,
        status: 'idle',
        settings: null,
    });

    async function open(): Promise<void> {
        state.isOpen = true;
        state.status = 'loading';

        try {
            const page = usePage();
            const response = await fetch('/settings/invoicing', {
                headers: {
                    'X-Inertia': 'true',
                    'X-Inertia-Version': String(page.version ?? ''),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            // 409 = Inertia asset version mismatch. Follow the server's redirect hint if given,
            // otherwise force a full reload — the SPA bundle is stale, retrying the fetch cannot fix it.
            if (response.status === 409) {
                const location = response.headers.get('X-Inertia-Location');
                if (location) {
                    window.location.href = location;
                } else {
                    window.location.reload();
                }
                return;
            }

            if (!response.ok) {
                throw new Error(`invoice settings request failed with status ${response.status}`);
            }

            const payload: unknown = await response.json();

            if (!isInertiaPartialPayload(payload)) {
                throw new Error('unexpected invoice settings response shape');
            }

            state.settings = payload.props.settings;
            state.status = 'ready';
        } catch {
            state.status = 'error';
        }
    }

    function close(): void {
        state.isOpen = false;
    }

    return { state, open, close };
}
