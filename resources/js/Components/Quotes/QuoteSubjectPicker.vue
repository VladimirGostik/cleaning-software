<script setup lang="ts">
    import { computed, reactive } from 'vue';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
    import QuoteManualRecipientFields, { type ManualRecipient } from './QuoteManualRecipientFields.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    export interface SubjectValue {
        client_id: string | null;
        cleaning_object_id: string | null;
        customer_name: string | null;
        customer_email: string | null;
        customer_street: string | null;
        customer_city: string | null;
        customer_postal_code: string | null;
    }

    interface ClientOption {
        id: string;
        name: string;
    }

    interface ObjectOption {
        id: string;
        name: string;
        client_id: string;
    }

    const props = withDefaults(
        defineProps<{
            modelValue: SubjectValue;
            clients: ClientOption[];
            objects?: ObjectOption[] | null;
            errors?: Record<string, string>;
        }>(),
        {
            objects: null,
            errors: () => ({}),
        },
    );

    const emit = defineEmits<{
        'update:modelValue': [value: SubjectValue];
    }>();

    const { t } = useTranslate();

    // Mode is genuinely local — not derivable from `modelValue` alone (a fresh
    // manual quote with an empty name is indistinguishable from a fresh client
    // quote). Initialised once from whichever branch already holds data.
    const ui = reactive({
        mode: (props.modelValue.client_id
            ? 'client'
            : props.modelValue.customer_name
              ? 'manual'
              : 'client') as 'client' | 'manual',
    });

    const modeOptions = computed<RadioOption[]>(() => [
        { value: 'client', label: t('quotes.subject.mode_client') },
        { value: 'manual', label: t('quotes.subject.mode_manual') },
    ]);

    const clientOptions = computed<SelectOption[]>(() =>
        props.clients.map((c) => ({ value: c.id, label: c.name })),
    );

    const objectOptions = computed<SelectOption[]>(() =>
        (props.objects ?? [])
            .filter((o) => o.client_id === props.modelValue.client_id)
            .map((o) => ({ value: o.id, label: o.name })),
    );

    const emptyManual: ManualRecipient = {
        customer_name: null,
        customer_email: null,
        customer_street: null,
        customer_city: null,
        customer_postal_code: null,
    };

    function setMode(mode: string): void {
        const nextMode = mode as 'client' | 'manual';
        ui.mode = nextMode;

        // The `prohibits` guard: after any mode switch exactly one branch is
        // non-null. Nulling only the opposite branch preserves whatever the
        // user already typed in the branch they are switching to.
        if (nextMode === 'client') {
            emit('update:modelValue', { ...props.modelValue, ...emptyManual });
        } else {
            emit('update:modelValue', { ...props.modelValue, client_id: null, cleaning_object_id: null });
        }
    }

    function setClientId(id: string | number): void {
        emit('update:modelValue', {
            ...props.modelValue,
            client_id: String(id) || null,
            cleaning_object_id: null,
        });
    }

    function setObjectId(id: string | number): void {
        emit('update:modelValue', {
            ...props.modelValue,
            cleaning_object_id: String(id) || null,
        });
    }

    const manualValue = computed<ManualRecipient>({
        get() {
            return {
                customer_name: props.modelValue.customer_name,
                customer_email: props.modelValue.customer_email,
                customer_street: props.modelValue.customer_street,
                customer_city: props.modelValue.customer_city,
                customer_postal_code: props.modelValue.customer_postal_code,
            };
        },
        set(value: ManualRecipient) {
            emit('update:modelValue', { ...props.modelValue, ...value });
        },
    });
</script>

<template>
    <div class="space-y-4">
        <RadioGroup
            :model-value="ui.mode"
            :options="modeOptions"
            :label="t('quotes.subject.mode')"
            @update:model-value="setMode"
        />

        <template v-if="ui.mode === 'client'">
            <SelectInput
                :model-value="modelValue.client_id ?? ''"
                :options="clientOptions"
                :label="t('quotes.subject.client')"
                :error="errors['client_id']"
                :placeholder="t('quotes.subject.client_placeholder')"
                required
                @update:model-value="setClientId($event)"
            />

            <SelectInput
                :model-value="modelValue.cleaning_object_id ?? ''"
                :options="objectOptions"
                :label="t('quotes.subject.object')"
                :error="errors['cleaning_object_id']"
                :placeholder="t('quotes.subject.object_placeholder')"
                :disabled="!modelValue.client_id"
                @update:model-value="setObjectId($event)"
            />
        </template>

        <QuoteManualRecipientFields v-else v-model="manualValue" :errors="errors" />
    </div>
</template>
