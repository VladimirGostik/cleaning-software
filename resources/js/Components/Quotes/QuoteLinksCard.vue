<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
import ContractStatusBadge from '@/Components/Contracts/ContractStatusBadge.vue';
import { formatDatetime } from '@/utils/date';

const props = defineProps<{
    quote: App.Data.Quotes.QuoteDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-3 text-sm">
            <h2 class="card-title text-base">{{ t('quote_section_links') }}</h2>

            <p v-if="props.quote.client_id">
                <span class="text-base-content/60">{{ t('quote_link_client') }}:</span>
                <a :href="`/clients/${props.quote.client_id}`" class="link link-hover ml-1">
                    {{ props.quote.client_name }}
                </a>
            </p>

            <p v-if="props.quote.object_name">
                <span class="text-base-content/60">{{ t('quote_section_object') }}:</span>
                <span class="ml-1">{{ props.quote.object_name }}</span>
            </p>

            <div>
                <p class="text-base-content/60">{{ t('quote_link_invoices') }}</p>
                <ul v-if="props.quote.invoices.length > 0" class="mt-1 space-y-1">
                    <li v-for="invoice in props.quote.invoices" :key="invoice.id" class="flex items-center gap-2">
                        <a :href="`/invoices/${invoice.id}`" class="link link-hover">
                            {{ invoice.number ?? t('invoice_draft_number') }}
                        </a>
                        <InvoiceStatusBadge :status="invoice.status" />
                    </li>
                </ul>
                <p v-else class="mt-1 text-base-content/50">{{ t('quote_link_no_invoices') }}</p>
            </div>

            <div>
                <p class="text-base-content/60">{{ t('quote_link_contracts') }}</p>
                <ul v-if="props.quote.contracts.length > 0" class="mt-1 space-y-1">
                    <li v-for="contract in props.quote.contracts" :key="contract.id" class="flex items-center gap-2">
                        <a :href="`/contracts/${contract.id}`" class="link link-hover">
                            {{ contract.title }}
                        </a>
                        <ContractStatusBadge :status="contract.status" />
                    </li>
                </ul>
                <p v-else class="mt-1 text-base-content/50">{{ t('quote_link_no_contracts') }}</p>
            </div>

            <div class="divider my-1" />

            <ul class="space-y-1 text-base-content/60">
                <li>
                    {{ props.quote.sent_at ? t('quote_sent_at') : t('quote_not_sent') }}
                    <template v-if="props.quote.sent_at">: {{ formatDatetime(props.quote.sent_at) }}</template>
                </li>
                <li v-if="props.quote.accepted_at">
                    {{ t('quote_accepted_at') }}: {{ formatDatetime(props.quote.accepted_at) }}
                </li>
                <li v-if="props.quote.rejected_at">
                    {{ t('quote_rejected_at') }}: {{ formatDatetime(props.quote.rejected_at) }}
                </li>
            </ul>
        </div>
    </div>
</template>
