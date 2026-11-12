<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import UploadIcon from '~icons/material-symbols/upload-file-outline-rounded';
import ListRow from '@/Components/ListRow.vue';
import QuoteKindBadge from '@/Components/Quotes/QuoteKindBadge.vue';
import QuotePeriodTotalsInline from '@/Components/Quotes/QuotePeriodTotalsInline.vue';
import QuoteStatusBadge from '@/Components/Quotes/QuoteStatusBadge.vue';
import { formatDate } from '@/utils/date';

const props = defineProps<{
    quotes: App.Data.Quotes.QuoteListItemData[] | null;
    canCreate: boolean;
    /** Query string that prefills the create form, e.g. `client_id=…`. */
    createQuery: string;
}>();

const { t } = useI18n();
</script>

<template>
    <div v-if="props.quotes !== null || props.canCreate" class="card bg-base-100">
        <div class="card-body space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="card-title">{{ t('quotes') }}</h2>
                <div v-if="props.canCreate" class="flex flex-wrap gap-2">
                    <Link :href="`/quotes/create?kind=document&${props.createQuery}`" class="btn btn-neutral btn-sm">
                        <UploadIcon />
                        {{ t('quote_upload_pdf') }}
                    </Link>
                    <Link :href="`/quotes/create?${props.createQuery}`" class="btn btn-primary btn-sm">
                        {{ t('quote_add') }}
                    </Link>
                </div>
            </div>

            <p v-if="props.quotes !== null && props.quotes.length === 0" class="text-sm text-muted">
                {{ t('quotes_empty') }}
            </p>

            <ul v-else-if="props.quotes !== null" class="kg-list">
                <li v-for="quote in props.quotes" :key="quote.id">
                    <ListRow
                        :title="quote.subject ?? quote.number ?? t('quote_no_number')"
                        :href="`/quotes/${quote.id}`"
                    >
                        <template #description>
                            <span v-if="quote.number && quote.subject" class="tabular-nums">{{ quote.number }} · </span>
                            <span v-if="quote.object_name">{{ quote.object_name }} · </span>
                            {{ formatDate(quote.issue_date) }}
                            <template v-if="quote.kind === 'itemized'">
                                ·
                                <QuotePeriodTotalsInline
                                    :period-totals="quote.period_totals"
                                    :fallback-total="quote.total"
                                    :currency="quote.currency"
                                />
                            </template>
                        </template>
                        <template #trailing>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <QuoteKindBadge :kind="quote.kind" :has-document="quote.has_document" />
                                <QuoteStatusBadge v-if="quote.kind === 'itemized'" :status="quote.status" />
                            </div>
                        </template>
                    </ListRow>
                </li>
            </ul>
        </div>
    </div>
</template>
