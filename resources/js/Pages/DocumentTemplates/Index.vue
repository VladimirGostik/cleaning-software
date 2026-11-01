<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { PencilSquareIcon, RectangleStackIcon } from '@heroicons/vue/24/outline';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ObjectStatusBadge from '@/Components/Objects/ObjectStatusBadge.vue';
import DocumentTemplateScopeBadge from '@/Components/DocumentTemplates/DocumentTemplateScopeBadge.vue';
import DocumentTemplateStateBadge from '@/Components/DocumentTemplates/DocumentTemplateStateBadge.vue';
import DocumentTypeBadge from '@/Components/DocumentTemplates/DocumentTypeBadge.vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { formatDatetime } from '@/utils/date';
import {
    DOCUMENT_TEMPLATE_SCOPES,
    TEMPLATABLE_DOCUMENT_TYPES,
    documentTemplateScopeKey,
    documentTypeKey,
    employmentTypeKey,
} from '@/utils/enums';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    templates: Paginator<App.Data.DocumentTemplates.DocumentTemplateListItemData>;
    filters?: Record<string, unknown>;
    canCreateGlobal: boolean;
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('document_templates') }];

const hasActiveFilters = computed(() => Object.keys(readSpatieQuery().filters).length > 0);
const showEmptyState = computed(() => props.templates.total === 0 && !hasActiveFilters.value);

const columns: TableColumn<App.Data.DocumentTemplates.DocumentTemplateListItemData>[] = [
    { key: 'name', label: t('name'), sortable: true },
    { key: 'type', label: t('type'), sortable: true },
    { key: 'scope', label: t('document_template_scope') },
    { key: 'is_active', label: t('status') },
    { key: 'updated_at', label: t('updated_at'), sortable: true },
];

const filterDefinitions = computed<FilterConfig[]>(() => [
    { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
    {
        property: 'type',
        label: t('type'),
        type: 'select',
        placeholder: t('document_template_select_type'),
        defaultOperator: '=',
        options: TEMPLATABLE_DOCUMENT_TYPES.map((v) => ({ value: v, label: t(documentTypeKey(v)) })),
    },
    {
        property: 'scope',
        label: t('document_template_scope'),
        type: 'select',
        placeholder: t('document_template_select_scope'),
        defaultOperator: '=',
        options: DOCUMENT_TEMPLATE_SCOPES.map((v) => ({ value: v, label: t(documentTemplateScopeKey(v)) })),
    },
    { property: 'is_active', label: t('status'), type: 'boolean', defaultOperator: '=' },
]);
</script>

<template>
    <Header :title="t('document_templates')" :breadcrumbs="breadcrumbs">
        <template #actions>
            <Link
                v-if="canCreateGlobal"
                href="/settings/document-templates/create?global=1"
                class="btn btn-outline btn-sm"
            >
                {{ t('document_template_add_global') }}
            </Link>
            <Link
                v-if="allows('create document_templates')"
                href="/settings/document-templates/create"
                class="btn btn-primary btn-sm"
            >
                {{ t('document_template_add') }}
            </Link>
        </template>
    </Header>

    <EmptyState
        v-if="showEmptyState"
        :title="t('document_templates_empty')"
        :description="t('document_templates_empty_hint')"
        :icon="RectangleStackIcon"
    >
        <template #cta>
            <Link
                v-if="allows('create document_templates')"
                href="/settings/document-templates/create"
                class="btn btn-primary btn-sm"
            >
                {{ t('document_template_add') }}
            </Link>
        </template>
    </EmptyState>

    <div v-else class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <DataTable
                :columns="columns"
                :rows="templates"
                :filters="filterDefinitions"
                :row-href="(row) => `/settings/document-templates/${row.id}/edit`"
            >
                <template #cell-name="{ row }">
                    <Link :href="`/settings/document-templates/${row.id}/edit`" class="link link-hover font-medium">{{
                        row.name
                    }}</Link>
                </template>

                <template #cell-type="{ row }">
                    <div class="flex flex-wrap items-center gap-1">
                        <DocumentTypeBadge :type="row.type" />
                        <span v-if="row.employment_type" class="badge badge-sm badge-ghost">{{
                            t(employmentTypeKey(row.employment_type))
                        }}</span>
                    </div>
                </template>

                <template #cell-scope="{ row }">
                    <div class="flex flex-wrap items-center gap-1">
                        <DocumentTemplateScopeBadge :scope="row.scope" />
                        <DocumentTemplateStateBadge v-if="row.state && row.state !== 'base'" :state="row.state" />
                    </div>
                </template>

                <template #cell-is_active="{ row }">
                    <ObjectStatusBadge :is-active="row.is_active" />
                </template>

                <template #cell-updated_at="{ row }">{{ formatDatetime(row.updated_at) }}</template>

                <template #buttons="{ row }">
                    <Link
                        :href="`/settings/document-templates/${row.id}/edit`"
                        class="btn btn-ghost btn-xs"
                        :title="t('edit')"
                        :aria-label="t('edit')"
                    >
                        <PencilSquareIcon class="size-4" />
                    </Link>
                </template>
            </DataTable>
        </div>
    </div>
</template>
