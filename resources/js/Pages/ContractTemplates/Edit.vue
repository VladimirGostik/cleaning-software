<script setup lang="ts">
    import { Link } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import ContractTemplateForm from '@/Components/ContractTemplates/ContractTemplateForm.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

    interface Props {
        template: App.Data.ContractTemplates.ContractTemplateDetailData;
        categoryOptions: SelectOption[];
        clientContractTokens: { token: string; label: string }[];
        employmentContractTokens: { token: string; label: string }[];
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
</script>

<template>
    <div class="page-container">
        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/contract-templates">{{ t('contract_templates.title') }}</Link>
                </li>
                <li>
                    <Link :href="`/contract-templates/${props.template.id}`">
                        {{ props.template.name }}
                    </Link>
                </li>
                <li>{{ t('contract_templates.edit') }}</li>
            </ul>
        </div>

        <PageHeader :title="t('contract_templates.edit')" />

        <ContractTemplateForm
            :template="props.template"
            :category-options="props.categoryOptions"
            :client-contract-tokens="props.clientContractTokens"
            :employment-contract-tokens="props.employmentContractTokens"
        />
    </div>
</template>
