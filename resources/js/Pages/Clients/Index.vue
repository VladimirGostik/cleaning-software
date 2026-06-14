<script setup lang="ts">
    import { reactive, computed } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import { PlusIcon, UsersIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import Can from '@/Components/Can.vue';
    import ClientTypeBadge from '@/Components/Clients/ClientTypeBadge.vue';
    import ClientFiltersBar from '@/Components/Clients/ClientFiltersBar.vue';
    import ClientFormDrawer from '@/Components/Clients/ClientFormDrawer.vue';
    import Pagination from '@/Components/Pagination.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useClientFilters } from '@/Composables/useClientFilters';
    import type { PaginatedData } from '@/types/pagination';

    interface Props {
        clients: PaginatedData<App.Data.Clients.ClientListItemData>;
        filters: { search?: string | null; type?: App.Enums.ClientTypeEnum | null; sort?: string; per_page?: number };
        types: Array<{ value: string; label: string }>;
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    const { state: filterState } = useClientFilters(props.filters);

    const drawerState = reactive<{
        open: boolean;
        mode: 'create' | 'edit';
        client: App.Data.Clients.ClientDetailData | null;
    }>({
        open: false,
        mode: 'create',
        client: null,
    });

    function openCreate() {
        drawerState.mode = 'create';
        drawerState.client = null;
        drawerState.open = true;
    }

    function goToDetail(id: string) {
        router.get(`/clients/${id}`);
    }

    function onDrawerSaved() {
        drawerState.open = false;
        router.reload({ only: ['clients'] });
    }

    const subtitle = computed(() => {
        const total = props.clients.meta.total;
        const corporate = props.clients.data.filter((c) => c.type === 'corporate').length;
        const privateCnt = props.clients.data.filter((c) => c.type === 'private').length;
        return t('clients.subtitle')
            .replace('{count}', String(total))
            .replace('{corporate}', String(corporate))
            .replace('{private}', String(privateCnt));
    });

    const meta = computed(() => props.clients.meta);
    const links = computed(() => props.clients.links);
</script>

<template>
    <div class="page-container">
            <div v-if="flash.success" class="alert alert-success mb-4">
                <span>{{ flash.success }}</span>
            </div>

            <PageHeader :title="t('clients.title')" :subtitle="subtitle">
                <template #actions>
                    <Can permission="create clients">
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="openCreate"
                        >
                            <PlusIcon class="w-4 h-4" />
                            {{ t('clients.add') }}
                        </button>
                    </Can>
                </template>
            </PageHeader>

            <ClientFiltersBar
                v-model:search="filterState.search"
                v-model:type="filterState.type"
                :types="types"
            />

            <!-- Desktop table -->
            <div class="hidden md:block">
                <div class="overflow-x-auto">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>{{ t('clients.col.client') }}</th>
                                <th>{{ t('clients.col.type') }}</th>
                                <th>{{ t('clients.col.ico') }}</th>
                                <th>{{ t('clients.col.objects') }}</th>
                                <th>{{ t('clients.col.active_contracts') }}</th>
                                <th>{{ t('clients.col.email') }}</th>
                                <th>{{ t('clients.col.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in clients.data"
                                :key="row.id"
                                class="hover cursor-pointer"
                                @click="goToDetail(row.id)"
                            >
                                <td>
                                    <p class="font-medium">{{ row.name }}</p>
                                    <p v-if="row.city" class="text-xs opacity-60">{{ row.city }}</p>
                                </td>
                                <td>
                                    <ClientTypeBadge :type="row.type" />
                                </td>
                                <td>{{ row.ico ?? t('common.empty_dash') }}</td>
                                <td>{{ row.objects_count }}</td>
                                <td>
                                    <span
                                        v-if="row.active_contracts_count > 0"
                                        class="badge badge-success badge-sm"
                                    >
                                        {{ row.active_contracts_count }}
                                    </span>
                                    <span v-else>{{ t('common.empty_dash') }}</span>
                                </td>
                                <td>{{ row.primary_contact_email ?? t('common.empty_dash') }}</td>
                                <td @click.stop>
                                    <Can permission="edit clients">
                                        <Link
                                            :href="`/clients/${row.id}`"
                                            class="btn btn-ghost btn-xs"
                                        >
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                    </Can>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile cards -->
            <div class="md:hidden space-y-3">
                <div
                    v-for="row in clients.data"
                    :key="row.id"
                    class="card bg-base-100 shadow-sm cursor-pointer"
                    @click="goToDetail(row.id)"
                >
                    <div class="card-body p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-medium">{{ row.name }}</p>
                                <p v-if="row.city" class="text-xs opacity-60">{{ row.city }}</p>
                            </div>
                            <ClientTypeBadge :type="row.type" />
                        </div>
                        <div class="flex gap-3 text-sm mt-2 text-base-content/70">
                            <span v-if="row.ico">{{ t('clients.col.ico') }}: {{ row.ico }}</span>
                            <span v-if="row.primary_contact_email">{{ row.primary_contact_email }}</span>
                            <span v-if="row.primary_contact_phone">{{ row.primary_contact_phone }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <EmptyState
                v-if="clients.data.length === 0"
                :title="t('clients.empty')"
                :description="t('clients.empty_hint')"
                :icon="UsersIcon"
            >
                <template #cta>
                    <Can permission="create clients">
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="openCreate"
                        >
                            <PlusIcon class="w-4 h-4" />
                            {{ t('clients.add') }}
                        </button>
                    </Can>
                </template>
            </EmptyState>

            <!-- Pagination -->
            <Pagination v-if="clients.data.length > 0" :meta="meta" :links="links" />

            <ClientFormDrawer
                v-if="drawerState.open"
                :mode="drawerState.mode"
                :client="drawerState.client"
                @close="drawerState.open = false"
                @saved="onDrawerSaved"
            />
        </div>
</template>
