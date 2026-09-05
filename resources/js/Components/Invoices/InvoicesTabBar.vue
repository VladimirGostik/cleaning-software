<script setup lang="ts">
    // No Ziggy — hardcoded URL strings per project convention (see Clients/Index.vue, useInvoiceFilters.ts)
    import { Link } from '@inertiajs/vue3';
    import Can from '@/Components/Can.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { InvoiceTab } from '@/Composables/useInvoiceFilters';

    defineProps<{
        active: InvoiceTab;
        counts?: {
            all?: number | null;
            all_issued?: number | null;
            recurring?: number | null;
            drafts?: number | null;
            overdue?: number | null;
        };
    }>();

    const { t } = useTranslate();
</script>

<template>
    <div class="tabs tabs-bordered mb-4" role="tablist" :aria-label="t('invoices.title')">
        <Link
            href="/invoices"
            preserve-scroll
            role="tab"
            :aria-selected="active === 'all'"
            :class="['tab', active === 'all' ? 'tab-active' : '']"
        >
            {{ t('invoices.tab.all') }}
            <span v-if="counts?.all != null" class="badge badge-sm ml-1">{{ counts.all }}</span>
        </Link>

        <Link
            href="/invoices?tab=all_issued"
            preserve-scroll
            role="tab"
            :aria-selected="active === 'all_issued'"
            :class="['tab', active === 'all_issued' ? 'tab-active' : '']"
        >
            {{ t('invoices.tab.all_issued') }}
            <span v-if="counts?.all_issued != null" class="badge badge-sm ml-1">{{ counts.all_issued }}</span>
        </Link>

        <Link
            href="/invoices?tab=drafts"
            preserve-scroll
            role="tab"
            :aria-selected="active === 'drafts'"
            :class="['tab', active === 'drafts' ? 'tab-active' : '']"
        >
            {{ t('invoices.tab.drafts') }}
            <span v-if="counts?.drafts != null" class="badge badge-sm ml-1">{{ counts.drafts }}</span>
        </Link>

        <Link
            href="/invoices?tab=overdue"
            preserve-scroll
            role="tab"
            :aria-selected="active === 'overdue'"
            :class="['tab', active === 'overdue' ? 'tab-active' : '']"
        >
            {{ t('invoices.tab.overdue') }}
            <span v-if="counts?.overdue != null" class="badge badge-sm ml-1">{{ counts.overdue }}</span>
        </Link>

        <Can permission="view recurring_invoices">
            <Link
                href="/recurring-invoices"
                preserve-scroll
                role="tab"
                :aria-selected="active === 'recurring'"
                :class="['tab', active === 'recurring' ? 'tab-active' : '']"
            >
                {{ t('invoices.tab.recurring') }}
                <span v-if="counts?.recurring != null" class="badge badge-sm ml-1">{{
                    counts.recurring
                }}</span>
            </Link>
        </Can>
    </div>
</template>
