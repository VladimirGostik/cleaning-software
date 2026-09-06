<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import ContractBodyEditor from '@/Components/Contracts/ContractBodyEditor.vue';

import { CONTRACT_CATEGORIES, CONTRACT_CATEGORY_CONTRACTABLE, contractCategoryKey, enumOptions } from '@/utils/enums';

interface ContractTemplateFormData {
    name: string;
    category: App.Enums.ContractCategoryEnum;
    body: string;
    is_active: boolean;
}

const props = defineProps<{
    template?: App.Data.ContractTemplates.ContractTemplateDetailData | null;
    tokens: App.Data.Contracts.PlaceholderCatalogData;
}>();

const { t } = useI18n();

const isEditing = computed(() => !!props.template);

const form = useForm<ContractTemplateFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/contract-templates/${props.template!.id}` : '/contract-templates',
    {
        name: props.template?.name ?? '',
        category: props.template?.category ?? 'service_agreement',
        body: props.template?.body ?? '',
        is_active: props.template?.is_active ?? true,
    },
);

const categoryOptions = computed(() => enumOptions(CONTRACT_CATEGORIES, contractCategoryKey, t));

const activeTokens = computed<App.Data.Contracts.PlaceholderTokenData[]>(() => {
    const expected = CONTRACT_CATEGORY_CONTRACTABLE[form.category];

    if (expected === 'tenant_membership') return props.tokens.tenant_membership;
    if (expected === 'cleaning_object') return props.tokens.cleaning_object;

    const seen = new Set<string>();
    const union: App.Data.Contracts.PlaceholderTokenData[] = [];
    for (const token of [...props.tokens.cleaning_object, ...props.tokens.tenant_membership]) {
        if (!seen.has(token.token)) {
            seen.add(token.token);
            union.push(token);
        }
    }
    return union;
});
</script>

<template>
    <FormProvider :form="form">
        <form novalidate @submit.prevent="form.submit()">
            <div class="card bg-base-100 shadow-sm mb-6">
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <TextInput field="name" :label="t('contract_template_name')" required />
                        </div>

                        <SelectInput
                            field="category"
                            :label="t('contract_template_category')"
                            :options="categoryOptions"
                            required
                        />

                        <div>
                            <ToggleInput field="is_active" :label="t('contract_template_is_active')" />
                            <p class="text-xs text-base-content/60">{{ t('contract_template_inactive_hint') }}</p>
                        </div>
                    </div>

                    <ContractBodyEditor
                        :model-value="form.body"
                        :error="form.errors.body"
                        :tokens="activeTokens"
                        :label="t('contract_template_body')"
                        :placeholder="t('contract_template_body_placeholder')"
                        :hint="t('contract_body_resolve_hint')"
                        required
                        @update:model-value="form.body = $event"
                    />
                </div>
            </div>

            <FormActions
                :cancel-href="isEditing ? `/contract-templates/${props.template!.id}` : '/contract-templates'"
                :submit-label="isEditing ? t('save') : t('contract_template_add')"
                :processing="form.processing"
            />
        </form>
    </FormProvider>
</template>
