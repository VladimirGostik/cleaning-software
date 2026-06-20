<script setup lang="ts">
    import { computed } from 'vue';

    import { ref } from 'vue';
    import { router } from '@inertiajs/vue3';
    import { PlusIcon, FolderIcon } from '@heroicons/vue/24/outline';
    import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import Can from '@/Components/Can.vue';
    import Pagination from '@/Components/Pagination.vue';
    import ConfirmDialog from '@/Components/ConfirmDialog.vue';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { useContractTemplateFilters } from '@/Composables/useContractTemplateFilters';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useAuthorization } from '@/Composables/useAuthorization';
    import type { PaginatedData } from '@/types/pagination.d.ts';

    interface Props {
        templates: PaginatedData<App.Data.ContractTemplates.ContractTemplateListItemData>;
        filters: App.Data.ContractTemplates.ContractTemplateIndexFilterData;
        categoryOptions: SelectOption[];
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);
    const { can, hasFeature } = useAuthorization();

    const { state: filterState } = useContractTemplateFilters(props.filters);

    const meta = computed(() => props.templates.meta);
    const links = computed(() => props.templates.links);

    // eslint-disable-next-line no-restricted-syntax -- tracks which row's delete confirm dialog is open
    const deleteConfirmId = ref<string | null>(null);

    const categoryValue = computed<string>({
        get: () => filterState.category ?? '',
        set: (val: string | number) => {
            const str = String(val);
            filterState.category = str ? (str as App.Enums.ContractCategoryEnum) : undefined;
        },
    });

    const allCategoryOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('contracts.filter.all_categories') },
        ...props.categoryOptions,
    ]);

    function goToDetail(id: string): void {
        router.get(`/contract-templates/${id}`);
    }

    function deleteTemplate(id: string): void {
        router.delete(`/contract-templates/${id}`, {
            onSuccess: () => {
                deleteConfirmId.value = null;
            },
        });
    }

    function formatDate(dateStr: string): string {
        return new Date(dateStr).toLocaleDateString('sk-SK');
    }
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <PageHeader :title="t('contract_templates.title')">
            <template #actions>
                <Can permission="create contract_templates" feature="contracts">
                    <a href="/contract-templates/create" class="btn btn-primary">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('contract_templates.add') }}
                    </a>
                </Can>
            </template>
        </PageHeader>

        <!-- Inline filters (search + category) -->
        <div class="flex flex-wrap gap-3 mb-4 items-end">
            <label class="input flex items-center gap-2 flex-1 min-w-48">
                <MagnifyingGlassIcon class="w-4 h-4 opacity-60" />
                <input
                    v-model="filterState.search"
                    type="text"
                    :placeholder="t('contract_templates.search_placeholder')"
                    class="grow"
                />
            </label>

            <div class="flex-1 min-w-44">
                <SelectInput
                    v-model="categoryValue"
                    :options="allCategoryOptions"
                    :label="t('contract_templates.col.category')"
                />
            </div>
        </div>

        <!-- Table -->
        <div class="card bg-base-100 shadow-sm hidden md:block">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>{{ t('contract_templates.col.name') }}</th>
                            <th>{{ t('contract_templates.col.category') }}</th>
                            <th>{{ t('contract_templates.col.is_active') }}</th>
                            <th>{{ t('contract_templates.col.created_at') }}</th>
                            <th class="w-24" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in templates.data"
                            :key="row.id"
                            class="hover cursor-pointer"
                            role="button"
                            tabindex="0"
                            @click="goToDetail(row.id)"
                            @keydown.enter="goToDetail(row.id)"
                        >
                            <td class="font-medium">{{ row.name }}</td>
                            <td>
                                <span class="badge badge-ghost badge-sm">
                                    {{ t('contract_category.' + row.category) }}
                                </span>
                            </td>
                            <td>
                                <span
                                    :class="[
                                        'badge badge-sm',
                                        row.is_active ? 'badge-success' : 'badge-ghost',
                                    ]"
                                >
                                    {{ row.is_active ? t('common.yes') : t('common.no') }}
                                </span>
                            </td>
                            <td class="text-base-content/60 text-sm">
                                {{ formatDate(row.created_at) }}
                            </td>
                            <td>
                                <div class="flex gap-1" @click.stop>
                                    <Can permission="edit contract_templates">
                                        <a
                                            :href="`/contract-templates/${row.id}/edit`"
                                            class="btn btn-ghost btn-xs"
                                        >
                                            {{ t('contract_templates.action.edit') }}
                                        </a>
                                    </Can>
                                    <Can permission="delete contract_templates">
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-xs text-error"
                                            @click="deleteConfirmId = row.id"
                                        >
                                            {{ t('contract_templates.action.delete') }}
                                        </button>
                                    </Can>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden flex flex-col gap-2">
            <div
                v-for="row in templates.data"
                :key="row.id"
                class="card bg-base-100 shadow-sm p-4 cursor-pointer"
                role="button"
                tabindex="0"
                @click="goToDetail(row.id)"
                @keydown.enter="goToDetail(row.id)"
            >
                <div class="flex justify-between items-start">
                    <span class="font-medium">{{ row.name }}</span>
                    <span class="badge badge-ghost badge-sm">
                        {{ t('contract_category.' + row.category) }}
                    </span>
                </div>
                <div class="text-sm text-base-content/60 mt-1">{{ formatDate(row.created_at) }}</div>
            </div>
        </div>

        <EmptyState
            v-if="templates.data.length === 0"
            :title="t('contract_templates.empty')"
            :description="t('contract_templates.empty_hint')"
            :icon="FolderIcon"
        >
            <template v-if="can('create contract_templates') && hasFeature('contracts')" #cta>
                <a href="/contract-templates/create" class="btn btn-primary btn-sm">
                    {{ t('contract_templates.add') }}
                </a>
            </template>
        </EmptyState>

        <Pagination v-if="meta.last_page > 1" :meta="meta" :links="links" />

        <!-- Delete confirm dialog -->
        <ConfirmDialog
            :open="deleteConfirmId !== null"
            :title="t('contract_templates.action.delete')"
            :body="t('contract_templates.delete_confirm')"
            :confirm-label="t('contract_templates.action.delete')"
            :cancel-label="t('common.cancel')"
            confirm-variant="error"
            @confirm="deleteConfirmId && deleteTemplate(deleteConfirmId)"
            @cancel="deleteConfirmId = null"
        />
    </div>
</template>
