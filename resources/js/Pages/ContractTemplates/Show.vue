<script setup lang="ts">
    import { reactive, computed } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import Can from '@/Components/Can.vue';
    import ConfirmDialog from '@/Components/ConfirmDialog.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useLocalizedDate } from '@/Composables/useLocalizedDate';

    interface Props {
        template: App.Data.ContractTemplates.ContractTemplateDetailData;
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);
    const { formatDate } = useLocalizedDate();

    const ui = reactive({
        deleteConfirmOpen: false,
    });

    function deleteTemplate(): void {
        router.delete(`/contract-templates/${props.template.id}`, {
            onSuccess: () => router.visit('/contract-templates'),
        });
    }
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/contract-templates">{{ t('contract_templates.title') }}</Link>
                </li>
                <li>{{ template.name }}</li>
            </ul>
        </div>

        <PageHeader :title="template.name">
            <template #badges>
                <span class="badge badge-ghost">
                    {{ t('contract_category.' + template.category) }}
                </span>
                <span :class="['badge', template.is_active ? 'badge-success' : 'badge-ghost']">
                    {{ template.is_active ? t('common.yes') : t('common.no') }}
                </span>
            </template>
            <template #actions>
                <Can permission="edit contract_templates">
                    <a :href="`/contract-templates/${template.id}/edit`" class="btn btn-ghost btn-sm">
                        {{ t('contract_templates.action.edit') }}
                    </a>
                </Can>
                <Can permission="delete contract_templates">
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm text-error"
                        @click="ui.deleteConfirmOpen = true"
                    >
                        {{ t('contract_templates.action.delete') }}
                    </button>
                </Can>
            </template>
        </PageHeader>

        <!-- Body card -->
        <div class="card bg-base-100 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="card-title text-base mb-2">{{ t('contract_templates.form.body') }}</h2>
                <pre class="whitespace-pre-wrap font-mono text-sm bg-base-200/50 rounded-lg p-4">{{
                    template.body
                }}</pre>
            </div>
        </div>

        <!-- Meta info -->
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-base-content/60">
                            {{ t('contract_templates.col.category') }}
                        </dt>
                        <dd class="font-medium mt-0.5">
                            {{ t('contract_category.' + template.category) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-base-content/60">
                            {{ t('contract_templates.col.created_at') }}
                        </dt>
                        <dd class="font-medium mt-0.5">
                            {{ formatDate(template.created_at) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <ConfirmDialog
            :open="ui.deleteConfirmOpen"
            :title="t('contract_templates.action.delete')"
            :body="t('contract_templates.delete_confirm')"
            :confirm-label="t('contract_templates.action.delete')"
            :cancel-label="t('common.cancel')"
            confirm-variant="error"
            @confirm="deleteTemplate"
            @cancel="ui.deleteConfirmOpen = false"
        />
    </div>
</template>
