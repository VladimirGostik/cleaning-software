<script setup lang="ts">
    import { Link } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import ContractForm from '@/Components/Contracts/ContractForm.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

    interface Props {
        contract: App.Data.Contracts.ContractDetailData;
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
                <li>
                    <Link :href="`/contracts/${props.contract.id}`">{{ props.contract.title }}</Link>
                </li>
                <li>{{ t('contracts.edit') }}</li>
            </ul>
        </div>

        <PageHeader :title="t('contracts.edit')" />

        <div v-if="!props.contract.is_editable" class="alert alert-warning mb-4">
            <span>{{ t('contracts.edit.not_editable') }}</span>
        </div>

        <ContractForm
            :contract="props.contract"
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
