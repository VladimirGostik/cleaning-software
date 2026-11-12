<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerPartyDetails from '@/Components/Invoices/CustomerPartyDetails.vue';
import QuoteRoughBadge from './QuoteRoughBadge.vue';

const props = defineProps<{
    quote: App.Data.Quotes.QuoteDetailData;
}>();

const { t } = useI18n();

const buyerName = computed(() => props.quote.buyer.name ?? props.quote.customer_name);
const linksToClient = computed(() => props.quote.client_id !== null && props.quote.buyer.source === 'client');
</script>

<template>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <h3 class="text-sm font-medium text-muted">{{ t('quote_section_customer') }}</h3>

            <p class="flex items-center gap-2 font-medium">
                <Link v-if="linksToClient" :href="`/clients/${props.quote.client_id}`" class="link link-hover">
                    {{ buyerName }}
                </Link>
                <span v-else>{{ buyerName }}</span>
                <QuoteRoughBadge v-if="props.quote.client_id === null" />
            </p>

            <CustomerPartyDetails :party="{ ...props.quote.buyer, name: null }" />
        </div>

        <div>
            <h3 class="text-sm font-medium text-muted">{{ t('quote_section_object') }}</h3>
            <p>{{ props.quote.object_name ?? t('empty_dash') }}</p>
        </div>
    </div>
</template>
