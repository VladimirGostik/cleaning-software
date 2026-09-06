<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import { formatDatetime } from '@/utils/date';
import { formatBytes } from '@/utils/bytes';
import { EyeIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/solid';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn } from '@/types/table';

const { t } = useI18n();

defineProps<{
    media: Paginator<App.Data.MediaListItemData>;
    filters?: Record<string, unknown>;
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('media') }];

const columns: TableColumn<App.Data.MediaListItemData>[] = [
    { key: 'file_name', label: t('file_name'), sortable: true },
    { key: 'mime_type', label: t('mime_type'), sortable: true },
    { key: 'size', label: t('size'), sortable: true },
    { key: 'collection_name', label: t('collection_name'), sortable: false },
    { key: 'model_type_label', label: t('owner'), sortable: false },
    { key: 'model_url', label: t('parent_model'), sortable: false },
    { key: 'created_at', label: t('created_at'), sortable: true },
];

const filterDefinitions = computed<FilterConfig[]>(() => [
    {
        property: 'search',
        label: t('search'),
        type: 'text',
        placeholder: t('search'),
        defaultOperator: '~',
    },
    {
        property: 'collection_name',
        label: t('collection_name'),
        type: 'text',
        placeholder: t('select_collection'),
        defaultOperator: '=',
    },
    {
        property: 'model_type',
        label: t('model_type'),
        type: 'text',
        placeholder: t('select_model_type'),
        defaultOperator: '=',
    },
    {
        property: 'mime_type',
        label: t('mime_type'),
        type: 'text',
        placeholder: t('select_mime_type'),
        defaultOperator: '=',
    },
]);
</script>

<template>
    <AppLayout>
        <Header :title="t('media_library')" :breadcrumbs="breadcrumbs" />

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable :columns="columns" :rows="media" :filters="filterDefinitions" route-name="media.index">
                    <template #cell-file_name="{ row }">
                        <a
                            :href="`/media/${(row as App.Data.MediaListItemData).id}`"
                            class="link link-hover font-medium"
                        >
                            {{ (row as App.Data.MediaListItemData).file_name }}
                        </a>
                    </template>

                    <template #cell-mime_type="{ value }">
                        <span v-if="value" class="badge badge-ghost badge-sm">{{ value }}</span>
                        <span v-else class="text-base-content/40">—</span>
                    </template>

                    <template #cell-size="{ value }">
                        <span class="text-sm">{{ formatBytes(value as number) }}</span>
                    </template>

                    <template #cell-collection_name="{ value }">
                        <span class="badge badge-outline badge-sm">{{ value }}</span>
                    </template>

                    <template #cell-model_type_label="{ row }">
                        <span class="text-sm">{{ (row as App.Data.MediaListItemData).model_type_label }}</span>
                    </template>

                    <template #cell-model_url="{ row }">
                        <a
                            v-if="(row as App.Data.MediaListItemData).model_url"
                            :href="(row as App.Data.MediaListItemData).model_url!"
                            class="link link-primary text-sm inline-flex items-center gap-1"
                        >
                            <ArrowTopRightOnSquareIcon class="size-3" />
                            {{ t('open_owner') }}
                        </a>
                        <span v-else class="text-base-content/40">—</span>
                    </template>

                    <template #cell-created_at="{ value }">
                        <span class="text-sm text-base-content/70">{{ formatDatetime(value as string | null) }}</span>
                    </template>

                    <template #buttons="{ row }">
                        <a
                            :href="`/media/${(row as App.Data.MediaListItemData).id}`"
                            class="btn btn-ghost btn-xs"
                            :title="t('details')"
                        >
                            <EyeIcon class="size-4" />
                        </a>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
