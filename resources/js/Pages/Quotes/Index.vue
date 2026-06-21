<script setup lang="ts">
    import { computed } from 'vue';
    import { router } from '@inertiajs/vue3';
    import { PlusIcon, DocumentTextIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import Can from '@/Components/Can.vue';
    import Pagination from '@/Components/Pagination.vue';
    import QuoteStatusBadge from '@/Components/Quotes/QuoteStatusBadge.vue';
    import QuoteFilters from '@/Components/Quotes/QuoteFilters.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
    import type { PaginatedData } from '@/types/pagination';

    interface Props {
        quotes: PaginatedData<App.Data.Quotes.QuoteListItemData>;
        filters: {
            search?: string | null;
            status?: App.Enums.QuoteStatusEnum | null;
            client_id?: string | null;
            valid_from?: string | null;
            valid_to?: string | null;
        };
        statusOptions: SelectOption[];
        clients: App.Data.Clients.ClientOptionData[];
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    const meta = computed(() => props.quotes.meta);
    const links = computed(() => props.quotes.links);

    const subtitle = computed(() => t('quotes.subtitle').replace('{count}', String(props.quotes.meta.total)));

    function goToDetail(id: string) {
        router.get(`/quotes/${id}`);
    }
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <PageHeader :title="t('quotes.title')" :subtitle="subtitle">
            <template #actions>
                <Can permission="create quotes" feature="quotes">
                    <a href="/quotes/create" class="btn btn-primary">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('quotes.add') }}
                    </a>
                </Can>
            </template>
        </PageHeader>

        <QuoteFilters :filters="props.filters" :status-options="statusOptions" :clients="clients" />

        <!-- Desktop table -->
        <div class="card bg-base-100 shadow-sm hidden md:block">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>{{ t('quotes.col.number') }}</th>
                            <th>{{ t('quotes.col.customer') }}</th>
                            <th>{{ t('quotes.col.status') }}</th>
                            <th class="text-right">{{ t('quotes.col.total') }}</th>
                            <th>{{ t('quotes.col.issue_date') }}</th>
                            <th>{{ t('quotes.col.valid_until') }}</th>
                            <th class="w-10" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in quotes.data"
                            :key="row.id"
                            class="hover cursor-pointer"
                            role="button"
                            tabindex="0"
                            @click="goToDetail(row.id)"
                            @keydown.enter="goToDetail(row.id)"
                        >
                            <td>
                                <span class="font-medium font-mono text-sm">
                                    {{ row.number ?? t('quotes.draft_number') }}
                                </span>
                            </td>
                            <td>
                                <div class="font-medium">{{ row.customer_name }}</div>
                                <div v-if="row.object_name" class="text-xs text-base-content/50">
                                    {{ row.object_name }}
                                </div>
                            </td>
                            <td>
                                <QuoteStatusBadge :status="row.status" />
                            </td>
                            <td class="text-right font-mono font-medium">{{ row.total }}</td>
                            <td class="text-sm">{{ row.issue_date }}</td>
                            <td class="text-sm">{{ row.valid_until }}</td>
                            <td>
                                <ChevronRightIcon class="w-4 h-4 text-base-content/40" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="quotes.data.length > 0" class="card-body py-3">
                <Pagination :meta="meta" :links="links" />
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden space-y-3">
            <div
                v-for="row in quotes.data"
                :key="row.id"
                class="card bg-base-100 shadow-sm cursor-pointer"
                role="button"
                tabindex="0"
                @click="goToDetail(row.id)"
                @keydown.enter="goToDetail(row.id)"
            >
                <div class="card-body p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium font-mono text-sm">
                                {{ row.number ?? t('quotes.draft_number') }}
                            </p>
                            <p class="text-sm">{{ row.customer_name }}</p>
                            <p v-if="row.object_name" class="text-xs text-base-content/50">
                                {{ row.object_name }}
                            </p>
                        </div>
                        <QuoteStatusBadge :status="row.status" />
                    </div>
                    <div class="flex justify-between text-sm mt-2">
                        <span class="text-base-content/60">{{ row.valid_until }}</span>
                        <span class="font-mono font-semibold">{{ row.total }}</span>
                    </div>
                </div>
            </div>

            <Pagination v-if="quotes.data.length > 0" :meta="meta" :links="links" />
        </div>

        <EmptyState
            v-if="quotes.data.length === 0"
            :title="t('quotes.empty')"
            :description="t('quotes.empty_hint')"
            :icon="DocumentTextIcon"
        >
            <template #cta>
                <Can permission="create quotes" feature="quotes">
                    <a href="/quotes/create" class="btn btn-primary">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('quotes.add') }}
                    </a>
                </Can>
            </template>
        </EmptyState>
    </div>
</template>
