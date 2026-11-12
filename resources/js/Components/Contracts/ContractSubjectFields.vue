<script setup lang="ts">
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';

import {
    CONTRACT_CATEGORIES,
    CONTRACT_CATEGORY_CONTRACTABLE_TYPES,
    CONTRACTABLE_TYPES,
    contractableTypeKey,
    contractCategoryKey,
    enumOptions,
} from '@/utils/enums';

const props = withDefaults(
    defineProps<{
        context: App.Data.Contracts.ContractFormContextData;
        contractableType: App.Enums.ContractableTypeEnum;
        category: App.Enums.ContractCategoryEnum;
        currentContractableId: string;
        lockedLabel: string | null;
        categories?: readonly App.Enums.ContractCategoryEnum[];
        contractableTypes?: readonly App.Enums.ContractableTypeEnum[];
    }>(),
    {
        categories: () => CONTRACT_CATEGORIES,
        contractableTypes: () => CONTRACTABLE_TYPES,
    },
);

const emit = defineEmits<{
    'update:contractableType': [value: App.Enums.ContractableTypeEnum];
}>();

const { t } = useI18n();

const categoryOptions = computed(() =>
    enumOptions(
        props.categories.filter(
            (category) =>
                props.lockedLabel === null ||
                CONTRACT_CATEGORY_CONTRACTABLE_TYPES[category].includes(props.contractableType),
        ),
        contractCategoryKey,
        t,
    ),
);

const availableTypes = computed(() =>
    CONTRACT_CATEGORY_CONTRACTABLE_TYPES[props.category].filter(
        (type) =>
            props.contractableTypes.includes(type) &&
            (type !== 'tenant_membership' || props.context.memberships.length > 0),
    ),
);

watch(availableTypes, (types) => {
    if (props.lockedLabel === null && types.length > 0 && !types.includes(props.contractableType)) {
        emit('update:contractableType', types[0]);
    }
});

const contractableTypeOptions = computed<RadioOption[]>(() =>
    enumOptions(availableTypes.value, contractableTypeKey, t),
);

const hintKey = computed(() => {
    if (availableTypes.value.length !== 1) return null;
    const only = availableTypes.value[0];

    return `contract_contractable_fixed_${only === 'cleaning_object' ? 'object' : only === 'client' ? 'client' : 'membership'}`;
});

const objectOptions = computed<SelectOption[]>(() =>
    props.context.objects
        .filter((o) => o.is_active || o.id === props.currentContractableId)
        .map((o) => ({ value: o.id, label: o.client_name ? `${o.client_name} — ${o.name}` : o.name })),
);

const clientOptions = computed<SelectOption[]>(() =>
    props.context.clients.map((c) => ({ value: c.id, label: c.name })),
);

const membershipOptions = computed<SelectOption[]>(() =>
    props.context.memberships
        .filter((m) => m.is_active || m.id === props.currentContractableId)
        .map((m) => ({ value: m.id, label: m.label })),
);

const noMembershipAccess = computed(() => props.category === 'employment' && props.context.memberships.length === 0);
const noBreakdownHint = computed(() => props.category === 'service_agreement' && props.contractableType === 'client');
</script>

<template>
    <div class="space-y-4">
        <SelectInput field="category" :label="t('type')" :options="categoryOptions" required />

        <div v-if="lockedLabel !== null">
            <p class="text-sm font-medium text-muted">{{ t('contract_contractable') }}</p>
            <p class="font-medium">
                <span class="text-muted">{{ t(contractableTypeKey(contractableType)) }}:</span>
                {{ lockedLabel }}
            </p>
        </div>

        <template v-else>
            <p v-if="hintKey" class="text-sm text-muted">{{ t(hintKey) }}</p>
            <RadioGroup
                v-else-if="availableTypes.length > 1"
                :model-value="contractableType"
                :label="t('contract_contractable_type')"
                :options="contractableTypeOptions"
                @update:model-value="emit('update:contractableType', $event as App.Enums.ContractableTypeEnum)"
            />

            <p v-if="noMembershipAccess" class="alert alert-warning alert-soft text-sm" role="alert">
                {{ t('contract_no_membership_access') }}
            </p>
            <template v-else>
                <SelectInput
                    v-if="contractableType === 'cleaning_object'"
                    field="contractable_id"
                    :label="t('contract_contractable')"
                    :placeholder="t('contract_select_object')"
                    :options="objectOptions"
                    required
                />
                <SelectInput
                    v-else-if="contractableType === 'client'"
                    field="contractable_id"
                    :label="t('contract_contractable')"
                    :placeholder="t('contract_select_client')"
                    :options="clientOptions"
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
            </template>
        </template>

        <p v-if="noBreakdownHint" class="text-sm text-muted">{{ t('contract_client_no_breakdown_hint') }}</p>
    </div>
</template>
