<script setup lang="ts">
    import { computed } from 'vue';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    export interface SubjectValue {
        client_id: string | null;
        cleaning_object_id: string | null;
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

    const clientOptions = computed<SelectOption[]>(() =>
        props.clients.map((c) => ({ value: c.id, label: c.name })),
    );

    const objectOptions = computed<SelectOption[]>(() =>
        (props.objects ?? [])
            .filter((o) => o.client_id === props.modelValue.client_id)
            .map((o) => ({ value: o.id, label: o.name })),
    );

    function setClientId(id: string | number) {
        emit('update:modelValue', {
            client_id: String(id) || null,
            cleaning_object_id: null,
        });
    }

    function setObjectId(id: string | number) {
        emit('update:modelValue', {
            ...props.modelValue,
            cleaning_object_id: String(id) || null,
        });
    }
</script>

<template>
    <div class="space-y-4">
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
    </div>
</template>
