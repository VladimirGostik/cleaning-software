<script setup lang="ts">
    import { ref, watch, nextTick } from 'vue';
    import { XMarkIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
    import Can from '@/Components/Can.vue';

    const props = defineProps<{
        open: boolean;
        invoice: App.Data.Invoices.InvoiceListItemData | null;
    }>();

    const emit = defineEmits<{
        'update:open': [value: boolean];
        openDetail: [id: string];
        action: [routeName: string, id: string];
    }>();

    const { t } = useTranslate();

    // eslint-disable-next-line no-restricted-syntax -- drawer focus management, imperative DOM access
    const drawerRef = ref<HTMLElement | null>(null);

    watch(
        () => props.open,
        (val) => {
            if (val) nextTick(() => drawerRef.value?.focus());
        },
    );

    function close() {
        emit('update:open', false);
    }

    function openDetail() {
        if (props.invoice) {
            emit('openDetail', props.invoice.id);
        }
    }

    function confirmCancel() {
        if (!props.invoice) return;
        if (window.confirm(t('invoices.cancel_confirm'))) {
            emit('action', 'invoices.cancel', props.invoice.id);
        }
    }
</script>

<template>
    <Teleport to="body">
        <template v-if="open && invoice">
            <div class="fixed inset-0 bg-black/40 z-40" @click="close" />
            <aside
                ref="drawerRef"
                role="dialog"
                aria-modal="true"
                aria-labelledby="peek-drawer-title"
                tabindex="-1"
                class="fixed right-0 top-0 h-full w-[400px] max-w-full bg-base-100 shadow-xl z-50 flex flex-col"
                @keydown.escape="close"
            >
                <!-- Header -->
                <header class="sticky top-0 bg-base-100 border-b border-base-300 px-5 py-4 flex justify-between items-center shrink-0">
                    <h2 id="peek-drawer-title" class="text-base font-semibold font-mono">
                        {{ invoice.number ?? t('invoices.draft_number') }}
                    </h2>
                    <button
                        type="button"
                        class="btn btn-sm btn-ghost btn-circle"
                        :aria-label="t('common.close')"
                        @click="close"
                    >
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </header>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto p-5 space-y-4">
                    <!-- Status -->
                    <div class="flex items-center gap-2">
                        <InvoiceStatusBadge :status="invoice.status" />
                        <span class="badge badge-ghost badge-sm">{{ t('invoice_type.' + invoice.type) }}</span>
                    </div>

                    <!-- Customer / Object -->
                    <div class="space-y-1">
                        <p class="text-sm font-medium">{{ invoice.customer_name }}</p>
                        <p v-if="invoice.object_name" class="text-xs text-base-content/50">{{ invoice.object_name }}</p>
                    </div>

                    <div class="divider my-0" />

                    <!-- Amounts / Dates -->
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-base-content/50 mb-0.5">{{ t('invoices.col.total') }}</dt>
                            <dd class="font-mono font-semibold">{{ invoice.total }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-base-content/50 mb-0.5">{{ t('invoices.col.issue_date') }}</dt>
                            <dd>{{ invoice.issue_date }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-base-content/50 mb-0.5">{{ t('invoices.col.due_date') }}</dt>
                            <dd :class="{ 'text-error font-medium': invoice.status === 'overdue' }">
                                {{ invoice.due_date }}
                            </dd>
                        </div>
                    </dl>

                    <!-- Inline status actions -->
                    <Can permission="edit invoices" feature="invoices">
                        <div class="flex flex-wrap gap-2 pt-1">
                            <button
                                v-if="invoice.status === 'draft'"
                                type="button"
                                class="btn btn-sm btn-outline"
                                @click="emit('action', 'invoices.issue', invoice.id)"
                            >
                                {{ t('invoices.action.issue') }}
                            </button>
                            <button
                                v-if="invoice.status === 'issued'"
                                type="button"
                                class="btn btn-sm btn-outline btn-success"
                                @click="emit('action', 'invoices.pay', invoice.id)"
                            >
                                {{ t('invoices.action.mark_paid') }}
                            </button>
                            <button
                                v-if="invoice.status === 'issued' || invoice.status === 'overdue'"
                                type="button"
                                class="btn btn-sm btn-outline btn-error"
                                @click="confirmCancel"
                            >
                                {{ t('invoices.action.cancel') }}
                            </button>
                        </div>
                    </Can>
                </div>

                <!-- Footer -->
                <footer class="shrink-0 border-t border-base-300 px-5 py-3 flex justify-between items-center">
                    <button type="button" class="btn btn-ghost btn-sm" @click="close">
                        {{ t('common.close') }}
                    </button>
                    <button type="button" class="btn btn-primary btn-sm gap-1" @click="openDetail">
                        <ArrowTopRightOnSquareIcon class="w-4 h-4" />
                        {{ t('invoices.peek.open_detail') }}
                    </button>
                </footer>
            </aside>
        </template>
    </Teleport>
</template>
