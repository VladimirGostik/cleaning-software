<script setup lang="ts">
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import { useFormContext } from '@/Components/Forms/useFormContext';

export type SubjectMode = 'client' | 'object' | 'standalone';

const props = defineProps<{
    mode: SubjectMode;
    clients: readonly App.Data.Clients.ClientOptionData[];
    objects: readonly App.Data.Objects.ObjectOptionData[];
}>();

const emit = defineEmits<{
    'update:mode': [mode: SubjectMode];
}>();

const { t } = useI18n();
const form = useFormContext();

const modeOptions = computed<RadioOption[]>(() => [
    { value: 'client', label: t('invoice_subject_mode_client') },
    { value: 'object', label: t('invoice_subject_mode_object') },
    { value: 'standalone', label: t('invoice_subject_mode_standalone') },
]);

const clientOptions = computed<SelectOption[]>(() => props.clients.map((c) => ({ value: c.id, label: c.name })));

const selectedClientId = computed<string | null>(() =>
    form ? ((form as Record<string, unknown>).client_id as string | null) : null,
);

const objectOptions = computed<SelectOption[]>(() => {
    if (!form) return [];
    const currentObjectId = (form as Record<string, unknown>).cleaning_object_id as string | null;
    return props.objects
        .filter((o) => o.client_id === selectedClientId.value && (o.is_active || o.id === currentObjectId))
        .map((o) => ({ value: o.id, label: o.name }));
});

function clearCustomerFields(): void {
    if (!form) return;
    const f = form as Record<string, unknown>;
    f.customer_name = null;
    f.customer_representative = null;
    f.customer_ico = null;
    f.customer_dic = null;
    f.customer_vat_number = null;
    f.customer_street = null;
    f.customer_city = null;
    f.customer_postal_code = null;
    f.customer_country = null;
    f.customer_email = null;
}

function onModeChange(mode: string): void {
    const next = mode as SubjectMode;
    if (!form) return;
    const f = form as Record<string, unknown>;

    if (next === 'standalone') {
        f.client_id = null;
        f.cleaning_object_id = null;
    } else if (next === 'client') {
        clearCustomerFields();
        f.cleaning_object_id = null;
    } else {
        clearCustomerFields();
    }

    emit('update:mode', next);
}

if (form) {
    watch(
        () => (form as Record<string, unknown>).client_id,
        () => {
            (form as Record<string, unknown>).cleaning_object_id = null;
        },
    );
}
</script>

<template>
    <div class="space-y-4">
        <RadioGroup
            :model-value="props.mode"
            :label="t('type')"
            :options="modeOptions"
            @update:model-value="onModeChange"
        />

        <SelectInput
            v-if="props.mode === 'client' || props.mode === 'object'"
            field="client_id"
            :label="t('client')"
            :options="clientOptions"
            :placeholder="t('select_client')"
            required
        />

        <SelectInput
            v-if="props.mode === 'object'"
            field="cleaning_object_id"
            :label="t('invoice_select_object')"
            :options="objectOptions"
            :placeholder="t('invoice_select_object')"
            :disabled="!selectedClientId"
            required
        />

        <div v-if="props.mode === 'standalone'" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <TextInput field="customer_name" :label="t('invoice_customer_name')" required />
            <TextInput
                field="customer_representative"
                :label="t('invoice_customer_representative')"
                :placeholder="t('invoice_customer_representative_placeholder')"
            />
            <TextInput field="customer_ico" :label="t('client_ico')" />
            <TextInput field="customer_dic" :label="t('client_dic')" />
            <TextInput field="customer_vat_number" :label="t('client_vat_number')" />
            <TextInput field="customer_email" type="email" :label="t('email')" />
            <TextInput field="customer_street" :label="t('street')" />
            <TextInput field="customer_city" :label="t('city')" />
            <TextInput field="customer_postal_code" :label="t('postal_code')" />
            <TextInput field="customer_country" :label="t('country')" />
        </div>
    </div>
</template>
