<script setup lang="ts">
    import { reactive, computed } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import { PlusIcon, BuildingOffice2Icon, PencilSquareIcon } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import Can from '@/Components/Can.vue';
    import ObjectTypeBadge from '@/Components/Objects/ObjectTypeBadge.vue';
    import ObjectFiltersBar from '@/Components/Objects/ObjectFiltersBar.vue';
    import ObjectFormDrawer from '@/Components/Objects/ObjectFormDrawer.vue';
    import Pagination from '@/Components/Pagination.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useObjectFilters } from '@/Composables/useObjectFilters';
    import type { PaginatedData } from '@/types/pagination';

    interface Props {
        objects: PaginatedData<App.Data.Objects.ObjectListItemData>;
        filters: {
            search?: string | null;
            type?: App.Enums.ObjectTypeEnum | null;
            client_id?: string | null;
            is_active?: boolean | null;
            sort?: string;
            per_page?: number;
        };
        types: Array<{ value: string; label: string }>;
        clients: Array<{ id: string; name: string }>;
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    const { state: filterState } = useObjectFilters(props.filters);

    const drawerState = reactive<{
        open: boolean;
        mode: 'create' | 'edit';
        object: App.Data.Objects.ObjectDetailData | null;
    }>({
        open: false,
        mode: 'create',
        object: null,
    });

    function openCreate() {
        drawerState.mode = 'create';
        drawerState.object = null;
        drawerState.open = true;
    }

    function goToDetail(id: string) {
        router.get(`/objects/${id}`);
    }

    function onDrawerSaved() {
        drawerState.open = false;
        router.reload({ only: ['objects'] });
    }

    const subtitle = computed(() => {
        const total = props.objects.meta.total;
        return t('objects.subtitle').replace('{count}', String(total));
    });

    const meta = computed(() => props.objects.meta);
    const links = computed(() => props.objects.links);
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <PageHeader :title="t('objects.title')" :subtitle="subtitle">
            <template #actions>
                <Can permission="create objects" feature="objects">
                    <button type="button" class="btn btn-primary" @click="openCreate">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('objects.add') }}
                    </button>
                </Can>
            </template>
        </PageHeader>

        <ObjectFiltersBar
            v-model:search="filterState.search"
            v-model:type="filterState.type"
            v-model:client_id="filterState.client_id"
            v-model:is_active="filterState.is_active"
            :types="types"
            :clients="clients"
        />

        <!-- Desktop table -->
        <div class="hidden md:block">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>{{ t('objects.col.name') }}</th>
                            <th>{{ t('objects.col.type') }}</th>
                            <th>{{ t('objects.col.client') }}</th>
                            <th>{{ t('objects.col.area') }}</th>
                            <th>{{ t('objects.col.active') }}</th>
                            <th>{{ t('objects.col.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in objects.data"
                            :key="row.id"
                            class="hover cursor-pointer"
                            @click="goToDetail(row.id)"
                        >
                            <td>
                                <p class="font-medium">{{ row.name }}</p>
                                <p v-if="row.city" class="text-xs opacity-60">{{ row.city }}</p>
                            </td>
                            <td>
                                <ObjectTypeBadge :type="row.type" />
                            </td>
                            <td>{{ row.client_name ?? t('common.empty_dash') }}</td>
                            <td>{{ row.area_sqm ?? t('common.empty_dash') }}</td>
                            <td>
                                <span v-if="row.is_active" class="badge badge-success badge-sm">
                                    {{ t('objects.active') }}
                                </span>
                                <span v-else class="badge badge-ghost badge-sm">
                                    {{ t('objects.inactive') }}
                                </span>
                            </td>
                            <td @click.stop>
                                <Can permission="edit objects" feature="objects">
                                    <Link :href="`/objects/${row.id}`" class="btn btn-ghost btn-xs">
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
                v-for="row in objects.data"
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
                        <ObjectTypeBadge :type="row.type" />
                    </div>
                    <div class="flex gap-3 text-sm mt-2 text-base-content/70 flex-wrap">
                        <span v-if="row.client_name">{{ row.client_name }}</span>
                        <span v-if="row.area_sqm">{{ row.area_sqm }} m²</span>
                        <span :class="['badge badge-sm', row.is_active ? 'badge-success' : 'badge-ghost']">
                            {{ row.is_active ? t('objects.active') : t('objects.inactive') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <EmptyState
            v-if="objects.data.length === 0"
            :title="t('objects.empty')"
            :description="t('objects.empty_hint')"
            :icon="BuildingOffice2Icon"
        >
            <template #cta>
                <Can permission="create objects" feature="objects">
                    <button type="button" class="btn btn-primary" @click="openCreate">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('objects.add') }}
                    </button>
                </Can>
            </template>
        </EmptyState>

        <!-- Pagination -->
        <Pagination v-if="objects.data.length > 0" :meta="meta" :links="links" />

        <ObjectFormDrawer
            v-if="drawerState.open"
            :mode="drawerState.mode"
            :object="drawerState.object"
            :clients="clients"
            @close="drawerState.open = false"
            @saved="onDrawerSaved"
        />
    </div>
</template>
