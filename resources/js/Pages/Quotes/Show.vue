<script setup lang="ts">
    import { computed, reactive } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import {
        PencilSquareIcon,
        DocumentArrowDownIcon,
        DocumentDuplicateIcon,
        CheckCircleIcon,
        XCircleIcon,
        PaperAirplaneIcon,
        TrashIcon,
        UserIcon,
        BuildingOfficeIcon,
        ReceiptPercentIcon,
        ClipboardDocumentListIcon,
    } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import Can from '@/Components/Can.vue';
    import ConfirmDialog from '@/Components/ConfirmDialog.vue';
    import QuoteStatusBadge from '@/Components/Quotes/QuoteStatusBadge.vue';
    import InvoiceVatRecap from '@/Components/Invoices/InvoiceVatRecap.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';

    interface Props {
        quote: App.Data.Quotes.QuoteDetailData;
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    const ui = reactive({
        sendConfirmOpen: false,
        rejectConfirmOpen: false,
        deleteConfirmOpen: false,
        processing: false,
    });

    const isDraft = computed(() => props.quote.status === 'draft');
    const isSent = computed(() => props.quote.status === 'sent');
    const isAccepted = computed(() => props.quote.status === 'accepted');

    function post(url: string, options: { onSuccess?: () => void } = {}) {
        router.post(
            url,
            {},
            {
                onStart: () => {
                    ui.processing = true;
                },
                onFinish: () => {
                    ui.processing = false;
                },
                onSuccess: options.onSuccess,
            },
        );
    }

    function sendQuote() {
        post(`/quotes/${props.quote.id}/send`, {
            onSuccess: () => {
                ui.sendConfirmOpen = false;
            },
        });
    }

    function acceptQuote() {
        post(`/quotes/${props.quote.id}/accept`);
    }

    function rejectQuote() {
        post(`/quotes/${props.quote.id}/reject`, {
            onSuccess: () => {
                ui.rejectConfirmOpen = false;
            },
        });
    }

    function duplicateQuote() {
        post(`/quotes/${props.quote.id}/duplicate`);
    }

    function convertToInvoice() {
        post(`/quotes/${props.quote.id}/convert-invoice`);
    }

    function convertToContract() {
        post(`/quotes/${props.quote.id}/convert-contract`);
    }

    function deleteQuote() {
        router.delete(`/quotes/${props.quote.id}`, {
            onStart: () => {
                ui.processing = true;
            },
            onFinish: () => {
                ui.processing = false;
            },
            onSuccess: () => router.visit('/quotes'),
        });
    }

    function formatDate(d: string | null): string {
        if (!d) return '—';
        return new Date(d).toLocaleDateString('sk-SK');
    }

    function itemNet(item: App.Data.Quotes.QuoteItemData): string {
        const n = item.line_base ?? item.quantity * item.unit_price;
        return n.toFixed(2);
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
                    <Link href="/quotes">{{ t('quotes.title') }}</Link>
                </li>
                <li class="font-mono">{{ quote.number ?? t('quotes.draft_number') }}</li>
            </ul>
        </div>

        <PageHeader :title="quote.number ?? t('quotes.draft_number')" :subtitle="quote.customer_name">
            <template #badges>
                <QuoteStatusBadge :status="quote.status" />
                <span class="badge badge-ghost badge-sm">{{ quote.currency }}</span>
            </template>
        </PageHeader>

        <!-- Two-column layout -->
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6">
            <!-- LEFT: quote document card -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body gap-6">
                    <!-- Customer block -->
                    <div>
                        <p class="text-xs text-base-content/50 uppercase tracking-wide mb-1">
                            {{ t('quotes.section.customer') }}
                        </p>
                        <p class="font-medium">{{ quote.customer_name }}</p>
                    </div>

                    <!-- Object block -->
                    <div v-if="quote.object_name" class="text-sm bg-base-200/50 rounded-lg p-3">
                        <p class="text-base-content/50 text-xs mb-0.5">{{ t('quotes.section.object') }}</p>
                        <p class="font-medium">{{ quote.object_name }}</p>
                    </div>

                    <!-- Subject + dates -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                        <div v-if="quote.subject" class="col-span-2 sm:col-span-3">
                            <p class="text-base-content/50 text-xs mb-0.5">{{ t('quotes.form.subject') }}</p>
                            <p class="font-medium">{{ quote.subject }}</p>
                        </div>
                        <div>
                            <p class="text-base-content/50 text-xs mb-0.5">
                                {{ t('quotes.col.issue_date') }}
                            </p>
                            <p class="font-mono">{{ formatDate(quote.issue_date) }}</p>
                        </div>
                        <div>
                            <p class="text-base-content/50 text-xs mb-0.5">
                                {{ t('quotes.col.valid_until') }}
                            </p>
                            <p class="font-mono">{{ formatDate(quote.valid_until) }}</p>
                        </div>
                    </div>

                    <!-- Items table -->
                    <div class="overflow-x-auto">
                        <table class="table table-sm w-full">
                            <thead>
                                <tr>
                                    <th class="w-[30%]">{{ t('quotes.items.name') }}</th>
                                    <th>{{ t('quotes.items.description') }}</th>
                                    <th>{{ t('quotes.items.frequency') }}</th>
                                    <th class="text-right">{{ t('quotes.items.quantity') }}</th>
                                    <th>{{ t('quotes.items.unit') }}</th>
                                    <th class="text-right">{{ t('quotes.items.unit_price') }}</th>
                                    <th class="text-right">{{ t('quotes.items.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in quote.items" :key="item.id ?? item.name">
                                    <td class="font-medium">{{ item.name }}</td>
                                    <td class="text-sm text-base-content/70">
                                        {{ item.description ?? '—' }}
                                    </td>
                                    <td class="text-sm text-base-content/70">{{ item.frequency ?? '—' }}</td>
                                    <td class="font-mono text-right">{{ item.quantity }}</td>
                                    <td>{{ item.unit ?? '—' }}</td>
                                    <td class="font-mono text-right">{{ item.unit_price }}</td>
                                    <td class="font-mono text-right font-medium">{{ itemNet(item) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary totals -->
                    <div class="flex justify-end">
                        <dl class="space-y-1 text-sm min-w-[260px]">
                            <div class="flex justify-between gap-4">
                                <dt class="text-base-content/60">{{ t('quotes.items.subtotal') }}</dt>
                                <dd class="font-mono">{{ quote.subtotal }} {{ quote.currency }}</dd>
                            </div>
                            <div v-if="quote.is_vat_payer" class="py-1">
                                <InvoiceVatRecap :breakdown="quote.vat_breakdown" />
                            </div>
                            <div
                                class="flex justify-between gap-4 border-t border-base-300 pt-1 font-semibold text-base"
                            >
                                <dt>{{ t('quotes.items.total') }}</dt>
                                <dd class="font-mono">{{ quote.total }} {{ quote.currency }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Note -->
                    <div v-if="quote.note">
                        <p class="text-xs text-base-content/50 uppercase tracking-wide mb-1">
                            {{ t('quotes.form.note') }}
                        </p>
                        <p class="whitespace-pre-wrap text-sm">{{ quote.note }}</p>
                    </div>
                </div>
            </div>

            <!-- RIGHT sidebar -->
            <div class="space-y-4">
                <!-- Actions card -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body gap-2">
                        <h2 class="card-title text-sm">{{ t('quotes.section.actions') }}</h2>

                        <!-- Edit (Draft only) -->
                        <Can permission="edit quotes" feature="quotes">
                            <Link
                                v-if="isDraft"
                                :href="`/quotes/${quote.id}/edit`"
                                class="btn btn-primary btn-sm w-full justify-start"
                            >
                                <PencilSquareIcon class="w-4 h-4" />
                                {{ t('quotes.action.edit') }}
                            </Link>
                        </Can>

                        <!-- Send (Draft only) -->
                        <Can permission="send quotes" feature="quotes">
                            <button
                                v-if="isDraft"
                                type="button"
                                class="btn btn-success btn-sm w-full justify-start"
                                :disabled="ui.processing"
                                @click="ui.sendConfirmOpen = true"
                            >
                                <PaperAirplaneIcon class="w-4 h-4" />
                                {{ t('quotes.action.send') }}
                            </button>
                        </Can>

                        <!-- Accept (Sent only) -->
                        <Can permission="approve quotes" feature="quotes">
                            <button
                                v-if="isSent"
                                type="button"
                                class="btn btn-success btn-sm w-full justify-start"
                                :disabled="ui.processing"
                                @click="acceptQuote"
                            >
                                <CheckCircleIcon class="w-4 h-4" />
                                {{ t('quotes.action.accept') }}
                            </button>
                        </Can>

                        <!-- Reject (Sent only) -->
                        <Can permission="approve quotes" feature="quotes">
                            <button
                                v-if="isSent"
                                type="button"
                                class="btn btn-warning btn-sm w-full justify-start"
                                :disabled="ui.processing"
                                @click="ui.rejectConfirmOpen = true"
                            >
                                <XCircleIcon class="w-4 h-4" />
                                {{ t('quotes.action.reject') }}
                            </button>
                        </Can>

                        <!-- Convert to Invoice (Accepted only) -->
                        <Can permission="create invoices" feature="quotes">
                            <button
                                v-if="isAccepted"
                                type="button"
                                class="btn btn-primary btn-sm w-full justify-start"
                                :disabled="ui.processing"
                                @click="convertToInvoice"
                            >
                                <ReceiptPercentIcon class="w-4 h-4" />
                                {{ t('quotes.action.convert_invoice') }}
                            </button>
                        </Can>

                        <!-- Convert to Contract (Accepted only, requires object) -->
                        <Can permission="create contracts" feature="quotes">
                            <button
                                v-if="isAccepted"
                                type="button"
                                class="btn btn-primary btn-sm w-full justify-start"
                                :disabled="ui.processing || !quote.cleaning_object_id"
                                :title="
                                    !quote.cleaning_object_id
                                        ? t('quotes.convert.object_required')
                                        : undefined
                                "
                                @click="convertToContract"
                            >
                                <ClipboardDocumentListIcon class="w-4 h-4" />
                                {{ t('quotes.action.convert_contract') }}
                            </button>
                        </Can>

                        <!-- Download PDF (always) -->
                        <a
                            :href="`/quotes/${quote.id}/pdf`"
                            class="btn btn-ghost btn-sm w-full justify-start"
                            target="_blank"
                        >
                            <DocumentArrowDownIcon class="w-4 h-4" />
                            {{ t('quotes.action.download_pdf') }}
                        </a>

                        <!-- Duplicate (always) -->
                        <Can permission="create quotes" feature="quotes">
                            <button
                                type="button"
                                class="btn btn-ghost btn-sm w-full justify-start"
                                :disabled="ui.processing"
                                @click="duplicateQuote"
                            >
                                <DocumentDuplicateIcon class="w-4 h-4" />
                                {{ t('quotes.action.duplicate') }}
                            </button>
                        </Can>

                        <!-- Delete (Draft only) -->
                        <Can permission="delete quotes" feature="quotes">
                            <button
                                v-if="isDraft"
                                type="button"
                                class="btn btn-ghost btn-sm w-full justify-start text-error"
                                :disabled="ui.processing"
                                @click="ui.deleteConfirmOpen = true"
                            >
                                <TrashIcon class="w-4 h-4" />
                                {{ t('quotes.action.delete') }}
                            </button>
                        </Can>
                    </div>
                </div>

                <!-- Links card -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body gap-2">
                        <h2 class="card-title text-sm">{{ t('quotes.section.links') }}</h2>

                        <div class="flex items-center gap-2 text-sm">
                            <UserIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                            <Link :href="`/clients/${quote.client_id}`" class="link link-hover">
                                {{ quote.customer_name }}
                            </Link>
                        </div>

                        <div v-if="quote.object_name" class="flex items-center gap-2 text-sm">
                            <BuildingOfficeIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                            <span class="text-base-content/70">{{ quote.object_name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Send confirm -->
        <ConfirmDialog
            :open="ui.sendConfirmOpen"
            :title="t('quotes.action.send')"
            :body="t('quotes.send_confirm')"
            :confirm-label="t('quotes.action.send')"
            :cancel-label="t('cancel')"
            confirm-variant="primary"
            @confirm="sendQuote"
            @cancel="ui.sendConfirmOpen = false"
        />

        <!-- Reject confirm -->
        <ConfirmDialog
            :open="ui.rejectConfirmOpen"
            :title="t('quotes.action.reject')"
            :body="t('quotes.reject_confirm')"
            :confirm-label="t('quotes.action.reject')"
            :cancel-label="t('cancel')"
            confirm-variant="warning"
            @confirm="rejectQuote"
            @cancel="ui.rejectConfirmOpen = false"
        />

        <!-- Delete confirm -->
        <ConfirmDialog
            :open="ui.deleteConfirmOpen"
            :title="t('quotes.action.delete')"
            :body="t('quotes.delete_confirm')"
            :confirm-label="t('quotes.action.delete')"
            :cancel-label="t('cancel')"
            confirm-variant="error"
            @confirm="deleteQuote"
            @cancel="ui.deleteConfirmOpen = false"
        />
    </div>
</template>
