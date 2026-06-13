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
        EllipsisVerticalIcon,
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
    <div class="max-w-5xl mx-auto">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <!-- Breadcrumb -->
        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/invoices">{{ t('invoices.title') }}</Link>
                </li>
                <li>{{ invoice.number ?? t('invoices.draft_number') }}</li>
            </ul>
        </div>

        <PageHeader
            :title="invoice.number ?? t('invoices.draft_number')"
            :subtitle="invoice.customer_name"
        >
            <template #badges>
                <InvoiceStatusBadge :status="invoice.status" />
            </template>
            <template #actions>
                <!-- Edit (Draft only) -->
                <Can permission="edit invoices" feature="invoices">
                    <Link
                        v-if="isDraft"
                        :href="`/invoices/${invoice.id}/edit`"
                        class="btn btn-primary btn-sm"
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
                        class="btn btn-success btn-sm"
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
                        class="btn btn-success btn-sm"
                        @click="markPaid"
                    >
                        <CheckCircleIcon class="w-4 h-4" />
                        {{ t('invoices.action.mark_paid') }}
                    </button>
                </Can>

                <!-- Download PDF -->
                <a :href="`/invoices/${invoice.id}/pdf`" class="btn btn-ghost btn-sm" target="_blank">
                    <DocumentArrowDownIcon class="w-4 h-4" />
                    {{ t('invoices.action.download_pdf') }}
                </a>

                <!-- More actions dropdown -->
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-sm btn-square">
                        <EllipsisVerticalIcon class="w-5 h-5" />
                    </div>
                    <ul
                        tabindex="0"
                        class="dropdown-content menu bg-base-100 rounded-box z-10 w-48 p-2 shadow"
                    >
                        <!-- Send email -->
                        <Can permission="edit invoices" feature="invoices">
                            <li v-if="isIssued">
                                <button
                                    type="button"
                                    :disabled="!canSend"
                                    :title="!invoice.customer_email ? t('invoices.no_customer_email') : undefined"
                                    @click="canSend && (ui.sendConfirmOpen = true)"
                                >
                                    <EnvelopeIcon class="w-4 h-4" />
                                    {{ t('invoices.action.send_email') }}
                                </button>
                            </li>
                        </Can>

                        <!-- Duplicate -->
                        <li>
                            <button type="button" @click="duplicateInvoice">
                                <DocumentDuplicateIcon class="w-4 h-4" />
                                {{ t('invoices.action.duplicate') }}
                            </button>
                        </li>

                        <!-- Storno -->
                        <Can permission="cancel invoices" feature="invoices">
                            <li v-if="canCancel">
                                <button type="button" class="text-warning" @click="ui.cancelConfirmOpen = true">
                                    <XCircleIcon class="w-4 h-4" />
                                    {{ t('invoices.action.cancel') }}
                                </button>
                            </li>
                        </Can>

                        <!-- Delete (Draft) -->
                        <Can permission="cancel invoices" feature="invoices">
                            <li v-if="isDraft">
                                <button type="button" class="text-error" @click="ui.deleteConfirmOpen = true">
                                    {{ t('invoices.action.delete') }}
                                </button>
                            </li>
                        </Can>
                    </ul>
                </div>
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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Left: 2/3 -->
            <div class="md:col-span-2 space-y-6">
                <!-- Customer info -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoices.section.customer') }}</h2>
                        <dl class="space-y-1.5 text-sm mt-2">
                            <div class="flex gap-2">
                                <dt class="text-base-content/60 w-32 shrink-0">{{ t('invoices.detail.customer_name') }}</dt>
                                <dd class="font-medium">{{ invoice.customer_name }}</dd>
                            </div>
                            <div v-if="invoice.customer_ico" class="flex gap-2">
                                <dt class="text-base-content/60 w-32 shrink-0">{{ t('invoices.detail.ico') }}</dt>
                                <dd>{{ invoice.customer_ico }}</dd>
                            </div>
                            <div v-if="invoice.customer_dic" class="flex gap-2">
                                <dt class="text-base-content/60 w-32 shrink-0">{{ t('invoices.detail.dic') }}</dt>
                                <dd>{{ invoice.customer_dic }}</dd>
                            </div>
                            <div v-if="invoice.customer_vat_number" class="flex gap-2">
                                <dt class="text-base-content/60 w-32 shrink-0">{{ t('invoices.detail.vat_number') }}</dt>
                                <dd>{{ invoice.customer_vat_number }}</dd>
                            </div>
                            <div v-if="invoice.customer_email" class="flex gap-2">
                                <dt class="text-base-content/60 w-32 shrink-0">{{ t('invoices.detail.email') }}</dt>
                                <dd>{{ invoice.customer_email }}</dd>
                            </div>
                            <div
                                v-if="invoice.customer_street || invoice.customer_city"
                                class="flex gap-2"
                            >
                                <dt class="text-base-content/60 w-32 shrink-0">{{ t('invoices.detail.address') }}</dt>
                                <dd>
                                    <span v-if="invoice.customer_street">{{ invoice.customer_street }}, </span>
                                    <span v-if="invoice.customer_postal_code">{{ invoice.customer_postal_code }} </span>
                                    <span v-if="invoice.customer_city">{{ invoice.customer_city }}</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Object info (when linked) -->
                <div v-if="invoice.object_name" class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoices.section.object') }}</h2>
                        <dl class="space-y-1.5 text-sm mt-2">
                            <div class="flex gap-2">
                                <dt class="text-base-content/60 w-32 shrink-0">{{ t('invoices.detail.object_name') }}</dt>
                                <dd class="font-medium">{{ invoice.object_name }}</dd>
                            </div>
                            <div v-if="invoice.object_street || invoice.object_city" class="flex gap-2">
                                <dt class="text-base-content/60 w-32 shrink-0">{{ t('invoices.detail.address') }}</dt>
                                <dd>
                                    <span v-if="invoice.object_street">{{ invoice.object_street }}, </span>
                                    <span v-if="invoice.object_postal_code">{{ invoice.object_postal_code }} </span>
                                    <span v-if="invoice.object_city">{{ invoice.object_city }}</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Items table -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoices.section.items') }}</h2>
                        <div class="overflow-x-auto mt-2">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th class="w-[45%]">{{ t('invoices.items.description') }}</th>
                                        <th>{{ t('invoices.items.quantity') }}</th>
                                        <th>{{ t('invoices.items.unit') }}</th>
                                        <th class="text-right">{{ t('invoices.items.unit_price') }}</th>
                                        <th class="text-right">{{ t('invoices.items.total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in invoice.items" :key="item.id ?? item.description">
                                        <td>{{ item.description }}</td>
                                        <td>{{ item.quantity }}</td>
                                        <td>{{ item.unit ?? t('common.empty_dash') }}</td>
                                        <td class="text-right">{{ item.unit_price }}</td>
                                        <td class="text-right font-medium">{{ item.total ?? (item.quantity * item.unit_price).toFixed(2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Totals -->
                        <div class="flex justify-end mt-4">
                            <dl class="space-y-1 text-sm min-w-[220px]">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-base-content/60">{{ t('invoices.detail.subtotal') }}</dt>
                                    <dd>{{ invoice.subtotal }}</dd>
                                </div>
                                <template v-if="invoice.is_vat_payer">
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-base-content/60">
                                            {{ t('invoices.detail.vat') }}
                                            <span v-if="invoice.vat_rate">({{ invoice.vat_rate }}%)</span>
                                        </dt>
                                        <dd>{{ invoice.vat_amount }}</dd>
                                    </div>
                                </template>
                                <div class="flex justify-between gap-4 border-t border-base-300 pt-1 font-semibold text-base">
                                    <dt>{{ t('invoices.detail.total') }}</dt>
                                    <dd>{{ invoice.total }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Non-VAT payer clause -->
                        <p v-if="!invoice.is_vat_payer" class="text-xs text-base-content/60 mt-2">
                            {{ t('invoices.pdf.non_vat_payer_clause') }}
                        </p>
                    </div>
                </div>

                <!-- Note -->
                <div v-if="invoice.note" class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoices.detail.note') }}</h2>
                        <p class="whitespace-pre-wrap text-sm mt-2">{{ invoice.note }}</p>
                    </div>
                </div>
            </div>

            <!-- Right: 1/3 -->
            <div class="space-y-6">
                <!-- Dates & metadata -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoices.section.details') }}</h2>
                        <dl class="space-y-2 text-sm mt-2">
                            <div class="flex justify-between">
                                <dt class="text-base-content/60">{{ t('invoices.detail.type') }}</dt>
                                <dd>
                                    <span class="badge badge-ghost badge-sm">
                                        {{ t('invoice_type.' + invoice.type) }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-base-content/60">{{ t('invoices.col.issue_date') }}</dt>
                                <dd>{{ formatDate(invoice.issue_date) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-base-content/60">{{ t('invoices.detail.delivery_date') }}</dt>
                                <dd>{{ formatDate(invoice.delivery_date) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-base-content/60">{{ t('invoices.col.due_date') }}</dt>
                                <dd :class="{ 'text-error font-medium': invoice.status === 'overdue' }">
                                    {{ formatDate(invoice.due_date) }}
                                </dd>
                            </div>
                            <template v-if="invoice.period_from">
                                <div class="flex justify-between">
                                    <dt class="text-base-content/60">{{ t('invoices.detail.period') }}</dt>
                                    <dd>{{ formatDate(invoice.period_from) }} – {{ formatDate(invoice.period_to) }}</dd>
                                </div>
                            </template>
                            <div v-if="invoice.issued_at" class="flex justify-between">
                                <dt class="text-base-content/60">{{ t('invoices.detail.issued_at') }}</dt>
                                <dd>{{ formatDate(invoice.issued_at) }}</dd>
                            </div>
                            <div v-if="invoice.sent_at" class="flex justify-between">
                                <dt class="text-base-content/60">{{ t('invoices.detail.sent_at') }}</dt>
                                <dd>{{ formatDate(invoice.sent_at) }}</dd>
                            </div>
                            <div v-if="invoice.paid_at" class="flex justify-between">
                                <dt class="text-base-content/60">{{ t('invoices.detail.paid_at') }}</dt>
                                <dd class="text-success">{{ formatDate(invoice.paid_at) }}</dd>
                            </div>
                            <div v-if="invoice.cancelled_at" class="flex justify-between">
                                <dt class="text-base-content/60">{{ t('invoices.detail.cancelled_at') }}</dt>
                                <dd>{{ formatDate(invoice.cancelled_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Supplier info -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoices.section.supplier') }}</h2>
                        <dl class="space-y-1.5 text-sm mt-2">
                            <div class="font-medium">{{ invoice.supplier.name }}</div>
                            <div v-if="invoice.supplier.ico" class="text-base-content/60">
                                {{ t('invoices.detail.ico') }}: {{ invoice.supplier.ico }}
                            </div>
                            <div v-if="invoice.supplier.iban" class="font-mono text-xs">
                                {{ invoice.supplier.iban }}
                            </div>
                            <div v-if="invoice.variable_symbol" class="text-base-content/60 text-xs">
                                VS: {{ invoice.variable_symbol }}
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- QR code -->
                <div v-if="invoice.qr_data_uri" class="card bg-base-100 shadow-sm">
                    <div class="card-body items-center">
                        <h2 class="card-title text-base">{{ t('invoices.section.qr') }}</h2>
                        <img
                            :src="invoice.qr_data_uri"
                            :alt="t('invoices.section.qr')"
                            class="w-32 h-32 mt-2"
                        />
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
