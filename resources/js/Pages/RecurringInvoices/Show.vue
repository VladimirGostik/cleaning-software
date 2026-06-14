<script setup lang="ts">
    import { computed, reactive } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import { PencilSquareIcon, CheckIcon } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import Can from '@/Components/Can.vue';
    import ConfirmDialog from '@/Components/ConfirmDialog.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
    import RecurringStatusBadge from '@/Components/RecurringInvoices/RecurringStatusBadge.vue';
    import RecurringFrequencyBadge from '@/Components/RecurringInvoices/RecurringFrequencyBadge.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';

    interface Props {
        recurring: App.Data.RecurringInvoices.RecurringInvoiceDetailData;
        generatedInvoices: App.Data.Invoices.InvoiceListItemData[];
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    const ui = reactive({
        cancelConfirmOpen: false,
        deleteConfirmOpen: false,
    });

    const isActive = computed(() => props.recurring.status === 'active');
    const isPaused = computed(() => props.recurring.status === 'paused');
    const canModify = computed(() => isActive.value || isPaused.value);

    function pause(): void {
        router.post(`/recurring-invoices/${props.recurring.id}/pause`, {});
    }

    function resume(): void {
        router.post(`/recurring-invoices/${props.recurring.id}/resume`, {});
    }

    function cancelRecurring(): void {
        router.post(`/recurring-invoices/${props.recurring.id}/cancel`, {}, {
            onSuccess: () => { ui.cancelConfirmOpen = false; },
        });
    }

    function deleteRecurring(): void {
        router.delete(`/recurring-invoices/${props.recurring.id}`, {
            onSuccess: () => router.visit('/recurring-invoices'),
        });
    }

    function formatDate(d: string | null): string {
        if (!d) return t('common.empty_dash');
        return new Date(d).toLocaleDateString('sk-SK');
    }
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/recurring-invoices">{{ t('recurring_invoices.title') }}</Link>
                </li>
                <li>{{ recurring.name }}</li>
            </ul>
        </div>

        <PageHeader :title="recurring.name">
            <template #badges>
                <RecurringStatusBadge :status="recurring.status" />
                <RecurringFrequencyBadge :frequency="recurring.frequency" />
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6">
            <!-- LEFT: detail card -->
            <div class="space-y-6">
                <!-- Customer / subject block -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body gap-4">
                        <h2 class="card-title text-base">{{ t('invoices.section.customer') }}</h2>
                        <div class="text-sm space-y-1">
                            <p class="font-medium">{{ recurring.customer_name ?? t('recurring_invoices.no_customer') }}</p>
                            <p v-if="recurring.customer_representative" class="text-base-content/60">
                                {{ recurring.customer_representative }}
                            </p>
                            <p v-if="recurring.customer_ico" class="text-base-content/60">
                                {{ t('invoices.detail.ico') }}: {{ recurring.customer_ico }}
                            </p>
                            <p v-if="recurring.customer_dic" class="text-base-content/60">
                                {{ t('invoices.detail.dic') }}: {{ recurring.customer_dic }}
                            </p>
                            <p v-if="recurring.customer_vat_number" class="text-base-content/60">
                                {{ t('invoices.detail.vat_number') }}: {{ recurring.customer_vat_number }}
                            </p>
                            <p
                                v-if="recurring.customer_street || recurring.customer_city"
                                class="text-base-content/70"
                            >
                                <span v-if="recurring.customer_street">{{ recurring.customer_street }}, </span>
                                <span v-if="recurring.customer_postal_code">{{ recurring.customer_postal_code }} </span>
                                <span v-if="recurring.customer_city">{{ recurring.customer_city }}</span>
                            </p>
                            <p v-if="recurring.customer_email" class="text-base-content/60">
                                {{ recurring.customer_email }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Schedule summary card -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body gap-4">
                        <h2 class="card-title text-base">{{ t('recurring_invoices.form.schedule') }}</h2>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                            <div>
                                <p class="text-base-content/50 text-xs mb-0.5">{{ t('recurring_invoices.col.frequency') }}</p>
                                <RecurringFrequencyBadge :frequency="recurring.frequency" />
                            </div>
                            <div>
                                <p class="text-base-content/50 text-xs mb-0.5">{{ t('recurring_invoices.form.day_of_month') }}</p>
                                <p class="font-mono">{{ recurring.day_of_month }}.</p>
                            </div>
                            <div>
                                <p class="text-base-content/50 text-xs mb-0.5">{{ t('recurring_invoices.col.next_run') }}</p>
                                <p class="font-mono">{{ formatDate(recurring.next_run_at) }}</p>
                            </div>
                            <div>
                                <p class="text-base-content/50 text-xs mb-0.5">{{ t('recurring_invoices.col.occurrences') }}</p>
                                <p class="font-mono">
                                    {{ recurring.occurrences_generated }}/{{ recurring.occurrences_limit !== null ? recurring.occurrences_limit : '∞' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-base-content/50 text-xs mb-0.5">{{ t('recurring_invoices.form.auto_issue') }}</p>
                                <CheckIcon v-if="recurring.auto_issue" class="w-4 h-4 text-success" />
                                <span v-else class="text-base-content/30">—</span>
                            </div>
                            <div>
                                <p class="text-base-content/50 text-xs mb-0.5">{{ t('recurring_invoices.form.due_days') }}</p>
                                <p class="font-mono">{{ recurring.due_days }}</p>
                            </div>
                            <div>
                                <p class="text-base-content/50 text-xs mb-0.5">{{ t('recurring_invoices.form.start_date') }}</p>
                                <p class="font-mono">{{ formatDate(recurring.start_date) }}</p>
                            </div>
                            <div v-if="recurring.end_date">
                                <p class="text-base-content/50 text-xs mb-0.5">{{ t('recurring_invoices.form.end_date') }}</p>
                                <p class="font-mono">{{ formatDate(recurring.end_date) }}</p>
                            </div>
                            <div v-if="recurring.period_from">
                                <p class="text-base-content/50 text-xs mb-0.5">{{ t('invoices.detail.period') }}</p>
                                <p class="font-mono">{{ formatDate(recurring.period_from) }} – {{ formatDate(recurring.period_to) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items read-only table -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoices.form.items') }}</h2>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th class="w-[45%]">{{ t('invoices.items.description') }}</th>
                                        <th class="text-right">{{ t('invoices.items.quantity') }}</th>
                                        <th>{{ t('invoices.items.unit') }}</th>
                                        <th class="text-right">{{ t('invoices.items.unit_price') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, i) in recurring.items" :key="i">
                                        <td class="font-medium">{{ item.description }}</td>
                                        <td class="font-mono text-right">{{ item.quantity }}</td>
                                        <td>{{ item.unit ?? t('common.empty_dash') }}</td>
                                        <td class="font-mono text-right">{{ item.unit_price }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Note -->
                <div v-if="recurring.note" class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <p class="text-xs text-base-content/50 uppercase tracking-wide mb-1">
                            {{ t('invoices.detail.note') }}
                        </p>
                        <p class="whitespace-pre-wrap text-sm">{{ recurring.note }}</p>
                    </div>
                </div>

                <!-- Generated invoices card -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('recurring_invoices.generated_invoices') }}</h2>

                        <EmptyState
                            v-if="generatedInvoices.length === 0"
                            :title="t('recurring_invoices.no_generated_invoices')"
                            :description="''"
                        />

                        <div v-else class="space-y-2">
                            <Link
                                v-for="inv in generatedInvoices"
                                :key="inv.id"
                                :href="`/invoices/${inv.id}`"
                                class="flex items-center justify-between p-3 rounded-lg border border-base-300 hover:bg-base-200 transition"
                            >
                                <div>
                                    <p class="font-mono text-sm font-medium">{{ inv.number ?? t('invoices.draft_number') }}</p>
                                    <p class="text-xs text-base-content/60">{{ inv.customer_name }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-sm">{{ inv.total }}</span>
                                    <InvoiceStatusBadge :status="inv.status" />
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT sidebar: Akcie card -->
            <div class="space-y-4">
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body gap-2">
                        <h2 class="card-title text-sm">{{ t('invoices.section.actions') }}</h2>

                        <!-- Edit -->
                        <Can permission="edit recurring_invoices" feature="invoices">
                            <Link
                                v-if="canModify"
                                :href="`/recurring-invoices/${recurring.id}/edit`"
                                class="btn btn-primary btn-sm w-full justify-start"
                            >
                                <PencilSquareIcon class="w-4 h-4" />
                                {{ t('invoices.action.edit') }}
                            </Link>
                        </Can>

                        <!-- Pause -->
                        <Can permission="edit recurring_invoices" feature="invoices">
                            <button
                                v-if="isActive"
                                type="button"
                                class="btn btn-warning btn-sm w-full justify-start"
                                @click="pause"
                            >
                                {{ t('recurring_invoices.action.pause') }}
                            </button>
                        </Can>

                        <!-- Resume -->
                        <Can permission="edit recurring_invoices" feature="invoices">
                            <button
                                v-if="isPaused"
                                type="button"
                                class="btn btn-success btn-sm w-full justify-start"
                                @click="resume"
                            >
                                {{ t('recurring_invoices.action.resume') }}
                            </button>
                        </Can>

                        <!-- Cancel -->
                        <Can permission="delete recurring_invoices" feature="invoices">
                            <button
                                v-if="canModify"
                                type="button"
                                class="btn btn-ghost btn-sm w-full justify-start text-warning"
                                @click="ui.cancelConfirmOpen = true"
                            >
                                {{ t('recurring_invoices.action.cancel') }}
                            </button>
                        </Can>

                        <!-- Delete -->
                        <Can permission="delete recurring_invoices" feature="invoices">
                            <button
                                type="button"
                                class="btn btn-ghost btn-sm w-full justify-start text-error"
                                @click="ui.deleteConfirmOpen = true"
                            >
                                {{ t('invoices.action.delete') }}
                            </button>
                        </Can>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel confirm -->
        <ConfirmDialog
            :open="ui.cancelConfirmOpen"
            :title="t('recurring_invoices.action.cancel')"
            :body="t('recurring_invoices.cancel_confirm')"
            :confirm-label="t('recurring_invoices.action.cancel')"
            :cancel-label="t('common.cancel')"
            confirm-variant="warning"
            @confirm="cancelRecurring"
            @cancel="ui.cancelConfirmOpen = false"
        />

        <!-- Delete confirm -->
        <ConfirmDialog
            :open="ui.deleteConfirmOpen"
            :title="t('invoices.action.delete')"
            :body="t('recurring_invoices.delete_confirm')"
            :confirm-label="t('invoices.action.delete')"
            :cancel-label="t('common.cancel')"
            confirm-variant="error"
            @confirm="deleteRecurring"
            @cancel="ui.deleteConfirmOpen = false"
        />
    </div>
</template>
