<script setup lang="ts">
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import { useFormContext } from '@/Components/Forms/useFormContext';
import QuoteManualRecipientFields from './QuoteManualRecipientFields.vue';

export type QuoteSubjectMode = 'client' | 'object' | 'manual';

const SNAPSHOT_FIELDS = [
    'customer_name',
    'customer_email',
    'customer_street',
    'customer_city',
    'customer_postal_code',
] as const;

const props = defineProps<{
    mode: QuoteSubjectMode;
    clients: readonly App.Data.Clients.ClientOptionData[];
    objects: readonly App.Data.Objects.ObjectOptionData[];
}>();

const emit = defineEmits<{
    'update:mode': [mode: QuoteSubjectMode];
}>();

const { t } = useI18n();
const form = useFormContext();

const modeOptions = computed<RadioOption[]>(() => [
    { value: 'client', label: t('quote_subject_mode_client') },
    { value: 'object', label: t('quote_subject_mode_object') },
    { value: 'manual', label: t('quote_subject_mode_manual') },
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

function clearSnapshotFields(): void {
    if (!form) return;
    const f = form as Record<string, unknown>;
    SNAPSHOT_FIELDS.forEach((key) => {
        f[key] = null;
    });
}

function onModeChange(mode: string): void {
    const next = mode as QuoteSubjectMode;
    if (!form) return;
    const f = form as Record<string, unknown>;

    if (next === 'manual') {
        f.client_id = null;
        f.cleaning_object_id = null;
    } else if (next === 'client') {
        clearSnapshotFields();
        f.cleaning_object_id = null;
    } else {
        clearSnapshotFields();
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
            :label="t('quote_subject_mode')"
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
            :label="t('quote_object')"
            :options="objectOptions"
            :placeholder="t('invoice_select_object')"
            :disabled="!selectedClientId"
            required
        />

        <QuoteManualRecipientFields v-if="props.mode === 'manual'" />
    </div>
</template>
