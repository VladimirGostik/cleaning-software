<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import QuoteRoughBadge from './QuoteRoughBadge.vue';

const props = defineProps<{
    quote: App.Data.Quotes.QuoteDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <h3 class="text-sm font-semibold text-base-content/70">{{ t('quote_section_customer') }}</h3>

            <p class="flex items-center gap-2 font-medium">
                <a v-if="props.quote.client_id" :href="`/clients/${props.quote.client_id}`" class="link link-hover">
                    {{ props.quote.customer_name }}
                </a>
                <span v-else>{{ props.quote.customer_name }}</span>
                <QuoteRoughBadge v-if="props.quote.client_id === null" />
            </p>

            <template v-if="props.quote.client_id === null">
                <p v-if="props.quote.customer_street">{{ props.quote.customer_street }}</p>
                <p v-if="props.quote.customer_postal_code || props.quote.customer_city">
                    {{ [props.quote.customer_postal_code, props.quote.customer_city].filter(Boolean).join(' ') }}
                </p>
                <p v-if="props.quote.customer_email" class="text-sm text-base-content/60">
                    {{ props.quote.customer_email }}
                </p>
            </template>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-base-content/70">{{ t('quote_section_object') }}</h3>
            <p>{{ props.quote.object_name ?? t('empty_dash') }}</p>
        </div>
    </div>
</template>
