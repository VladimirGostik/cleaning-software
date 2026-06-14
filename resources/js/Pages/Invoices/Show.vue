<script setup lang="ts">
    import { computed, reactive } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import {
        PencilSquareIcon,
        DocumentArrowDownIcon,
        EnvelopeIcon,
        CheckCircleIcon,
        XCircleIcon,
        DocumentDuplicateIcon,
        UserIcon,
        BuildingOfficeIcon,
    } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import Can from '@/Components/Can.vue';
    import ConfirmDialog from '@/Components/ConfirmDialog.vue';
    import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';

    interface Props {
        invoice: App.Data.Invoices.InvoiceDetailData;
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    const ui = reactive({
        issueDialogOpen: false,
        customNumber: '',
        sendConfirmOpen: false,
        cancelConfirmOpen: false,
        deleteConfirmOpen: false,
    });

    const isDraft = computed(() => props.invoice.status === 'draft');
    const isIssued = computed(() => props.invoice.status === 'issued');
    const isOverdue = computed(() => props.invoice.status === 'overdue');
    const canMarkPaid = computed(() => isIssued.value || isOverdue.value);
    const canCancel = computed(() => isIssued.value || isOverdue.value);
    const canSend = computed(() => isIssued.value && !!props.invoice.customer_email);

    function issueInvoice() {
        router.post(
            `/invoices/${props.invoice.id}/issue`,
            { number: ui.customNumber || null },
            {
                onSuccess: () => {
                    ui.issueDialogOpen = false;
                    ui.customNumber = '';
                },
            },
        );
    }

    function markPaid() {
        router.post(`/invoices/${props.invoice.id}/pay`, {});
    }

    function cancelInvoice() {
        router.post(`/invoices/${props.invoice.id}/cancel`, {}, {
            onSuccess: () => { ui.cancelConfirmOpen = false; },
        });
    }

    function sendInvoice() {
        router.post(`/invoices/${props.invoice.id}/send`, {}, {
            onSuccess: () => { ui.sendConfirmOpen = false; },
        });
    }

    function duplicateInvoice() {
        router.post(`/invoices/${props.invoice.id}/duplicate`, {});
    }

    function deleteInvoice() {
        router.delete(`/invoices/${props.invoice.id}`, {
            onSuccess: () => router.visit('/invoices'),
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

        <!-- Breadcrumb -->
        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/invoices">{{ t('invoices.title') }}</Link>
                </li>
                <li class="font-mono">{{ invoice.number ?? t('invoices.draft_number') }}</li>
            </ul>
        </div>

        <PageHeader
            :title="invoice.number ?? t('invoices.draft_number')"
            :subtitle="invoice.customer_name"
        >
            <template #badges>
                <InvoiceStatusBadge :status="invoice.status" />
                <span class="badge badge-ghost badge-sm">
                    {{ t('invoice_type.' + invoice.type) }}
                </span>
            </template>
        </PageHeader>

        <!-- Credit note link -->
        <div
            v-if="invoice.credited_invoice_id"
            class="alert alert-warning mb-4"
        >
            <span>
                {{ t('invoices.credit_note_for') }}
                <Link :href="`/invoices/${invoice.credited_invoice_id}`" class="link font-medium">
                    {{ t('invoices.view_original') }}
                </Link>
            </span>
        </div>

        <!-- Two-column layout -->
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6">
            <!-- LEFT: invoice document card -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body gap-6">
                    <!-- Header row: supplier left, QR + customer right -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Supplier block -->
                        <div>
                            <p class="font-semibold text-base">{{ invoice.supplier.name }}</p>
                            <p v-if="invoice.supplier.address_line" class="text-sm text-base-content/70">
                                {{ invoice.supplier.address_line }}
                            </p>
                            <p
                                v-if="invoice.supplier.postal_code || invoice.supplier.city"
                                class="text-sm text-base-content/70"
                            >
                                <span v-if="invoice.supplier.postal_code">{{ invoice.supplier.postal_code }} </span>
                                <span v-if="invoice.supplier.city">{{ invoice.supplier.city }}</span>
                            </p>
                            <p v-if="invoice.supplier.ico" class="text-sm text-base-content/60 mt-1">
                                {{ t('invoices.detail.ico') }}: {{ invoice.supplier.ico }}
                            </p>
                            <p v-if="invoice.supplier.dic" class="text-sm text-base-content/60">
                                {{ t('invoices.detail.dic') }}: {{ invoice.supplier.dic }}
                            </p>
                            <p v-if="invoice.supplier.vat_number" class="text-sm text-base-content/60">
                                {{ t('invoices.detail.vat_number') }}: {{ invoice.supplier.vat_number }}
                            </p>
                        </div>

                        <!-- QR + customer block -->
                        <div class="flex gap-4 sm:justify-end">
                            <img
                                v-if="invoice.qr_data_uri"
                                :src="invoice.qr_data_uri"
                                :alt="t('invoices.section.qr')"
                                class="w-20 h-20 shrink-0"
                            />
                            <div>
                                <p class="text-xs text-base-content/50 uppercase tracking-wide mb-1">
                                    {{ t('invoices.section.customer') }}
                                </p>
                                <p class="font-medium">{{ invoice.customer_name }}</p>
                                <p v-if="invoice.customer_ico" class="text-sm text-base-content/60">
                                    {{ t('invoices.detail.ico') }}: {{ invoice.customer_ico }}
                                </p>
                                <p v-if="invoice.customer_dic" class="text-sm text-base-content/60">
                                    {{ t('invoices.detail.dic') }}: {{ invoice.customer_dic }}
                                </p>
                                <p v-if="invoice.customer_vat_number" class="text-sm text-base-content/60">
                                    {{ t('invoices.detail.vat_number') }}: {{ invoice.customer_vat_number }}
                                </p>
                                <p
                                    v-if="invoice.customer_street || invoice.customer_city"
                                    class="text-sm text-base-content/70"
                                >
                                    <span v-if="invoice.customer_street">{{ invoice.customer_street }}, </span>
                                    <span v-if="invoice.customer_postal_code">{{ invoice.customer_postal_code }} </span>
                                    <span v-if="invoice.customer_city">{{ invoice.customer_city }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 4-up date grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                        <div>
                            <p class="text-base-content/50 text-xs mb-0.5">{{ t('invoices.col.issue_date') }}</p>
                            <p class="font-mono">{{ formatDate(invoice.issue_date) }}</p>
                        </div>
                        <div>
                            <p class="text-base-content/50 text-xs mb-0.5">{{ t('invoices.detail.delivery_date') }}</p>
                            <p class="font-mono">{{ formatDate(invoice.delivery_date) }}</p>
                        </div>
                        <div>
                            <p class="text-base-content/50 text-xs mb-0.5">{{ t('invoices.col.due_date') }}</p>
                            <p
                                class="font-mono"
                                :class="{ 'text-error font-medium': invoice.status === 'overdue' }"
                            >
                                {{ formatDate(invoice.due_date) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-base-content/50 text-xs mb-0.5">{{ t('invoices.detail.payment_method') }}</p>
                            <p>{{ t('invoices.detail.payment_transfer') }}</p>
                        </div>
                    </div>

                    <!-- Period row (when applicable) -->
                    <div v-if="invoice.period_from" class="text-sm">
                        <p class="text-base-content/50 text-xs mb-0.5">{{ t('invoices.detail.period') }}</p>
                        <p class="font-mono">{{ formatDate(invoice.period_from) }} – {{ formatDate(invoice.period_to) }}</p>
                    </div>

                    <!-- Object info -->
                    <div v-if="invoice.object_name" class="text-sm bg-base-200/50 rounded-lg p-3">
                        <p class="text-base-content/50 text-xs mb-0.5">{{ t('invoices.section.object') }}</p>
                        <p class="font-medium">{{ invoice.object_name }}</p>
                        <p
                            v-if="invoice.object_street || invoice.object_city"
                            class="text-base-content/60"
                        >
                            <span v-if="invoice.object_street">{{ invoice.object_street }}, </span>
                            <span v-if="invoice.object_postal_code">{{ invoice.object_postal_code }} </span>
                            <span v-if="invoice.object_city">{{ invoice.object_city }}</span>
                        </p>
                    </div>

                    <!-- Items table -->
                    <div class="overflow-x-auto">
                        <table class="table table-sm w-full">
                            <thead>
                                <tr>
                                    <th class="w-[45%]">{{ t('invoices.items.description') }}</th>
                                    <th class="text-right">{{ t('invoices.items.quantity') }}</th>
                                    <th>{{ t('invoices.items.unit') }}</th>
                                    <th class="text-right">{{ t('invoices.items.unit_price') }}</th>
                                    <th class="text-right">{{ t('invoices.items.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in invoice.items" :key="item.id ?? item.description">
                                    <td class="font-medium">{{ item.description }}</td>
                                    <td class="font-mono text-right">{{ item.quantity }}</td>
                                    <td>{{ item.unit ?? t('common.empty_dash') }}</td>
                                    <td class="font-mono text-right">{{ item.unit_price }}</td>
                                    <td class="font-mono text-right font-medium">
                                        {{ item.total ?? (item.quantity * item.unit_price).toFixed(2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary totals -->
                    <div class="flex justify-end">
                        <dl class="space-y-1 text-sm min-w-[220px]">
                            <div class="flex justify-between gap-4">
                                <dt class="text-base-content/60">{{ t('invoices.detail.subtotal') }}</dt>
                                <dd class="font-mono">{{ invoice.subtotal }}</dd>
                            </div>
                            <template v-if="invoice.is_vat_payer">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-base-content/60">
                                        {{ t('invoices.detail.vat') }}
                                        <span v-if="invoice.vat_rate">({{ invoice.vat_rate }}%)</span>
                                    </dt>
                                    <dd class="font-mono">{{ invoice.vat_amount }}</dd>
                                </div>
                            </template>
                            <div class="flex justify-between gap-4 border-t border-base-300 pt-1 font-semibold text-base">
                                <dt>{{ t('invoices.detail.total') }}</dt>
                                <dd class="font-mono">{{ invoice.total }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Non-VAT payer clause -->
                    <p v-if="!invoice.is_vat_payer" class="text-xs text-base-content/60">
                        {{ t('invoices.pdf.non_vat_payer_clause') }}
                    </p>

                    <!-- Pay block -->
                    <div
                        v-if="invoice.supplier.iban || invoice.variable_symbol"
                        class="bg-base-200/50 rounded-lg p-4 text-sm"
                    >
                        <p class="font-medium mb-2">{{ t('invoices.section.qr') }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div v-if="invoice.supplier.iban">
                                <p class="text-base-content/50 text-xs">{{ t('invoices.pdf.iban') }}</p>
                                <p class="font-mono">{{ invoice.supplier.iban }}</p>
                            </div>
                            <div v-if="invoice.variable_symbol">
                                <p class="text-base-content/50 text-xs">{{ t('invoices.pdf.variable_symbol') }}</p>
                                <p class="font-mono">{{ invoice.variable_symbol }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Note -->
                    <div v-if="invoice.note">
                        <p class="text-xs text-base-content/50 uppercase tracking-wide mb-1">
                            {{ t('invoices.detail.note') }}
                        </p>
                        <p class="whitespace-pre-wrap text-sm">{{ invoice.note }}</p>
                    </div>
                </div>
            </div>

            <!-- RIGHT sidebar -->
            <div class="space-y-4">
                <!-- Akcie card -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body gap-2">
                        <h2 class="card-title text-sm">{{ t('invoices.section.actions') }}</h2>

                        <!-- Edit (Draft only) -->
                        <Can permission="edit invoices" feature="invoices">
                            <Link
                                v-if="isDraft"
                                :href="`/invoices/${invoice.id}/edit`"
                                class="btn btn-primary btn-sm w-full justify-start"
                            >
                                <PencilSquareIcon class="w-4 h-4" />
                                {{ t('invoices.action.edit') }}
                            </Link>
                        </Can>

                        <!-- Issue (Draft only) -->
                        <Can permission="edit invoices" feature="invoices">
                            <button
                                v-if="isDraft"
                                type="button"
                                class="btn btn-success btn-sm w-full justify-start"
                                @click="ui.issueDialogOpen = true"
                            >
                                {{ t('invoices.action.issue') }}
                            </button>
                        </Can>

                        <!-- Mark paid -->
                        <Can permission="edit invoices" feature="invoices">
                            <button
                                v-if="canMarkPaid"
                                type="button"
                                class="btn btn-success btn-sm w-full justify-start"
                                @click="markPaid"
                            >
                                <CheckCircleIcon class="w-4 h-4" />
                                {{ t('invoices.action.mark_paid') }}
                            </button>
                        </Can>

                        <!-- Download PDF -->
                        <a
                            :href="`/invoices/${invoice.id}/pdf`"
                            class="btn btn-ghost btn-sm w-full justify-start"
                            target="_blank"
                        >
                            <DocumentArrowDownIcon class="w-4 h-4" />
                            {{ t('invoices.action.download_pdf') }}
                        </a>

                        <!-- Send email -->
                        <Can permission="edit invoices" feature="invoices">
                            <button
                                v-if="isIssued"
                                type="button"
                                class="btn btn-ghost btn-sm w-full justify-start"
                                :disabled="!canSend"
                                :title="!invoice.customer_email ? t('invoices.no_customer_email') : undefined"
                                @click="canSend && (ui.sendConfirmOpen = true)"
                            >
                                <EnvelopeIcon class="w-4 h-4" />
                                {{ t('invoices.action.send_email') }}
                            </button>
                        </Can>

                        <!-- Duplicate -->
                        <button
                            type="button"
                            class="btn btn-ghost btn-sm w-full justify-start"
                            @click="duplicateInvoice"
                        >
                            <DocumentDuplicateIcon class="w-4 h-4" />
                            {{ t('invoices.action.duplicate') }}
                        </button>

                        <!-- Storno -->
                        <Can permission="cancel invoices" feature="invoices">
                            <button
                                v-if="canCancel"
                                type="button"
                                class="btn btn-ghost btn-sm w-full justify-start text-warning"
                                @click="ui.cancelConfirmOpen = true"
                            >
                                <XCircleIcon class="w-4 h-4" />
                                {{ t('invoices.action.cancel') }}
                            </button>
                        </Can>

                        <!-- Delete (Draft) -->
                        <Can permission="cancel invoices" feature="invoices">
                            <button
                                v-if="isDraft"
                                type="button"
                                class="btn btn-ghost btn-sm w-full justify-start text-error"
                                @click="ui.deleteConfirmOpen = true"
                            >
                                {{ t('invoices.action.delete') }}
                            </button>
                        </Can>
                    </div>
                </div>

                <!-- Prepojenia card -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body gap-2">
                        <h2 class="card-title text-sm">{{ t('invoices.section.links') }}</h2>

                        <!-- Client link -->
                        <div v-if="invoice.client_id" class="flex items-center gap-2 text-sm">
                            <UserIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                            <Link :href="`/clients/${invoice.client_id}`" class="link link-hover">
                                {{ t('invoices.links.client') }}
                            </Link>
                        </div>

                        <!-- Object link (Track B: no route yet, show name as text) -->
                        <div v-if="invoice.object_name" class="flex items-center gap-2 text-sm">
                            <BuildingOfficeIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                            <span class="text-base-content/70">{{ invoice.object_name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Issue dialog -->
        <dialog class="modal" :open="ui.issueDialogOpen">
            <div class="modal-box">
                <h3 class="font-bold text-lg">{{ t('invoices.action.issue') }}</h3>
                <p class="text-sm text-base-content/60 mt-1">{{ t('invoices.issue_dialog.hint') }}</p>
                <div class="mt-4">
                    <label class="label">
                        <span class="label-text">{{ t('invoices.issue_dialog.custom_number') }}</span>
                    </label>
                    <input
                        v-model="ui.customNumber"
                        type="text"
                        :placeholder="t('invoices.issue_dialog.auto_placeholder')"
                        class="input w-full"
                    />
                </div>
                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" @click="ui.issueDialogOpen = false; ui.customNumber = ''">
                        {{ t('common.cancel') }}
                    </button>
                    <button type="button" class="btn btn-success" @click="issueInvoice">
                        {{ t('invoices.action.issue') }}
                    </button>
                </div>
            </div>
            <div class="modal-backdrop" @click="ui.issueDialogOpen = false; ui.customNumber = ''" />
        </dialog>

        <!-- Send email confirm -->
        <ConfirmDialog
            :open="ui.sendConfirmOpen"
            :title="t('invoices.action.send_email')"
            :body="t('invoices.send_confirm').replace('{email}', invoice.customer_email ?? '')"
            :confirm-label="t('invoices.action.send_email')"
            :cancel-label="t('common.cancel')"
            confirm-variant="primary"
            @confirm="sendInvoice"
            @cancel="ui.sendConfirmOpen = false"
        />

        <!-- Cancel / storno confirm -->
        <ConfirmDialog
            :open="ui.cancelConfirmOpen"
            :title="t('invoices.action.cancel')"
            :body="t('invoices.cancel_confirm')"
            :confirm-label="t('invoices.action.cancel')"
            :cancel-label="t('common.cancel')"
            confirm-variant="warning"
            @confirm="cancelInvoice"
            @cancel="ui.cancelConfirmOpen = false"
        />

        <!-- Delete confirm -->
        <ConfirmDialog
            :open="ui.deleteConfirmOpen"
            :title="t('invoices.action.delete')"
            :body="t('invoices.delete_confirm')"
            :confirm-label="t('invoices.action.delete')"
            :cancel-label="t('common.cancel')"
            confirm-variant="error"
            @confirm="deleteInvoice"
            @cancel="ui.deleteConfirmOpen = false"
        />
    </div>
</template>
