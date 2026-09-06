<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';

import {
    CONTRACT_CATEGORIES,
    CONTRACT_CATEGORY_CONTRACTABLE,
    CONTRACTABLE_TYPES,
    contractableTypeKey,
    contractCategoryKey,
    enumOptions,
} from '@/utils/enums';

const props = defineProps<{
    context: App.Data.Contracts.ContractFormContextData;
    contractableType: App.Enums.ContractableTypeEnum;
    category: App.Enums.ContractCategoryEnum;
    currentContractableId: string;
    templateBodyKept: boolean;
}>();

const emit = defineEmits<{
    'update:contractableType': [value: App.Enums.ContractableTypeEnum];
}>();

const { t } = useI18n();

const categoryOptions = computed(() => enumOptions(CONTRACT_CATEGORIES, contractCategoryKey, t));
const contractableTypeOptions = computed<RadioOption[]>(() => enumOptions(CONTRACTABLE_TYPES, contractableTypeKey, t));

const fixedContractableType = computed(() => CONTRACT_CATEGORY_CONTRACTABLE[props.category]);

const objectOptions = computed<SelectOption[]>(() =>
    props.context.objects
        .filter((o) => o.is_active || o.id === props.currentContractableId)
        .map((o) => ({ value: o.id, label: o.client_name ? `${o.client_name} — ${o.name}` : o.name })),
);

const membershipOptions = computed<SelectOption[]>(() =>
    props.context.memberships
        .filter((m) => m.is_active || m.id === props.currentContractableId)
        .map((m) => ({ value: m.id, label: m.label })),
);

const templateOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('contract_no_template') },
    ...props.context.templates
        .filter((tpl) => tpl.category === props.category)
        .map((tpl) => ({ value: tpl.id, label: tpl.name })),
]);
</script>

<template>
    <div class="space-y-4">
        <SelectInput field="category" :label="t('type')" :options="categoryOptions" required />

        <p v-if="fixedContractableType === 'cleaning_object'" class="text-sm text-base-content/60">
            {{ t('contract_contractable_fixed_object') }}
        </p>
        <p v-else-if="fixedContractableType === 'tenant_membership'" class="text-sm text-base-content/60">
            {{ t('contract_contractable_fixed_membership') }}
        </p>
        <RadioGroup
            v-else
            :model-value="contractableType"
            :label="t('contract_contractable_type')"
            :options="contractableTypeOptions"
            @update:model-value="emit('update:contractableType', $event as App.Enums.ContractableTypeEnum)"
        />

        <SelectInput
            v-if="contractableType === 'cleaning_object'"
            field="contractable_id"
            :label="t('contract_contractable')"
            :placeholder="t('contract_select_object')"
            :options="objectOptions"
            required
        />
        <SelectInput
            v-else
            field="contractable_id"
            :label="t('contract_contractable')"
            :placeholder="t('contract_select_membership')"
            :options="membershipOptions"
            required
        />

        <div>
            <SelectInput field="contract_template_id" :label="t('contract_template')" :options="templateOptions" />
            <p v-if="templateBodyKept" class="text-xs text-base-content/60">
                {{ t('contract_template_body_kept') }}
            </p>
        </div>
    </div>
</template>
