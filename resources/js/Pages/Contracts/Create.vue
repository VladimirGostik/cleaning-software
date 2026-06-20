<script setup lang="ts">
    import { Link } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import ContractForm from '@/Components/Contracts/ContractForm.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

    interface Props {
        templates: App.Data.ContractTemplates.ContractTemplateListItemData[];
        objects: App.Data.Objects.ObjectOptionData[];
        memberships: App.Data.Contracts.MembershipOptionData[];
        categoryOptions: SelectOption[];
        termTypeOptions: SelectOption[];
        employmentTypeOptions: SelectOption[];
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
                    <Link href="/contracts">{{ t('contracts.title') }}</Link>
                </li>
                <li>{{ t('contracts.create') }}</li>
            </ul>
        </div>

        <PageHeader :title="t('contracts.create')" />

        <ContractForm
            :templates="props.templates"
            :objects="props.objects"
            :memberships="props.memberships"
            :category-options="props.categoryOptions"
            :term-type-options="props.termTypeOptions"
            :employment-type-options="props.employmentTypeOptions"
            :client-contract-tokens="props.clientContractTokens"
            :employment-contract-tokens="props.employmentContractTokens"
        />
    </div>
</template>
