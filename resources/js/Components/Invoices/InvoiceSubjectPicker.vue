<script setup lang="ts">
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import FieldError from '@/Components/Forms/FieldError.vue';
import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import { useFormContext } from '@/Components/Forms/useFormContext';
import CustomerPartyDetails from '@/Components/Invoices/CustomerPartyDetails.vue';
import { useBuyerPreview } from '@/Composables/useBuyerPreview';

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

const selectedObjectId = computed<string | null>(() => {
    if (!form) return null;
    return ((form as Record<string, unknown>).cleaning_object_id as string | null) || null;
});

const { preview, loading, error } = useBuyerPreview(() => {
    if (props.mode === 'standalone' || !selectedClientId.value) return null;
    if (props.mode === 'object') {
        return selectedObjectId.value
            ? { client_id: selectedClientId.value, cleaning_object_id: selectedObjectId.value }
            : null;
    }
    return { client_id: selectedClientId.value, cleaning_object_id: null };
});

const sourceNote = computed<string | null>(() => {
    if (!preview.value) return null;
    if (preview.value.buyer.source === 'object_billing') return t('invoice_buyer_preview_source_object_billing');
    if (preview.value.buyer.source === 'client') return t('invoice_buyer_preview_source_client');
    return null;
});

function clearCustomerFields(): void {
    if (!form) return;
    const f = form as Record<string, unknown>;
    f.customer_name = null;
    f.customer_ico = null;
    f.customer_dic = null;
    f.customer_vat_number = null;
    f.customer_street = null;
    f.customer_city = null;
    f.customer_postal_code = null;
    f.customer_country = null;
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
        <FieldError v-if="props.mode !== 'standalone'" field="customer_name" />

        <SelectInput
            v-if="props.mode === 'object'"
            field="cleaning_object_id"
            :label="t('invoice_select_object')"
            :options="objectOptions"
            :placeholder="t('invoice_select_object')"
            :disabled="!selectedClientId"
            required
        />

        <div v-if="props.mode !== 'standalone' && (loading || error || preview)" class="rounded-box bg-base-200 p-4">
            <h3 class="text-sm font-semibold text-base-content/70">{{ t('invoice_buyer_preview_title') }}</h3>
            <span v-if="loading" class="loading loading-spinner loading-sm" />
            <div v-else-if="error" class="alert alert-warning mt-2">
                <span>{{ t('invoice_buyer_preview_failed') }}</span>
            </div>
            <template v-else-if="preview">
                <CustomerPartyDetails :party="preview.buyer" class="mt-2" />
                <p v-if="sourceNote" class="mt-2 text-xs text-base-content/60">{{ sourceNote }}</p>
            </template>
        </div>

        <div v-if="props.mode === 'standalone'" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <TextInput field="customer_name" :label="t('invoice_customer_name')" required />
            <TextInput field="customer_ico" :label="t('client_ico')" />
            <TextInput field="customer_dic" :label="t('client_dic')" />
            <TextInput field="customer_vat_number" :label="t('client_vat_number')" />
            <TextInput field="customer_street" :label="t('street')" />
            <TextInput field="customer_city" :label="t('city')" />
            <TextInput field="customer_postal_code" :label="t('postal_code')" />
            <TextInput field="customer_country" :label="t('country')" />
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <TextInput
                field="customer_representative"
                :label="t('invoice_customer_representative')"
                :placeholder="t('invoice_customer_representative_placeholder')"
            />
            <div>
                <TextInput
                    field="customer_email"
                    type="email"
                    :label="t('email')"
                    :suggestions="preview?.email_options"
                />
                <p class="mt-1 text-xs text-base-content/60">{{ t('invoice_customer_email_hint') }}</p>
            </div>
        </div>
    </div>
</template>
