<script setup lang="ts">
    import TextInput from '@/Components/Forms/TextInput.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    export interface ManualRecipient {
        customer_name: string | null;
        customer_email: string | null;
        customer_street: string | null;
        customer_city: string | null;
        customer_postal_code: string | null;
    }

    const props = withDefaults(
        defineProps<{
            modelValue: ManualRecipient;
            errors?: Record<string, string>;
        }>(),
        {
            errors: () => ({}),
        },
    );

    const emit = defineEmits<{
        'update:modelValue': [value: ManualRecipient];
    }>();

    const { t } = useTranslate();

    function update(key: keyof ManualRecipient, value: string): void {
        emit('update:modelValue', { ...props.modelValue, [key]: value || null });
    }
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <TextInput
                :model-value="modelValue.customer_name ?? ''"
                :label="t('quotes.subject.customer_name')"
                :error="errors['customer_name']"
                required
                @update:model-value="update('customer_name', $event)"
            />
        </div>

        <TextInput
            :model-value="modelValue.customer_email ?? ''"
            type="email"
            :label="t('quotes.subject.customer_email')"
            :error="errors['customer_email']"
            @update:model-value="update('customer_email', $event)"
        />

        <TextInput
            :model-value="modelValue.customer_street ?? ''"
            :label="t('quotes.subject.customer_street')"
            :error="errors['customer_street']"
            @update:model-value="update('customer_street', $event)"
        />

        <TextInput
            :model-value="modelValue.customer_city ?? ''"
            :label="t('quotes.subject.customer_city')"
            :error="errors['customer_city']"
            @update:model-value="update('customer_city', $event)"
        />

        <TextInput
            :model-value="modelValue.customer_postal_code ?? ''"
            :label="t('quotes.subject.customer_postal_code')"
            :error="errors['customer_postal_code']"
            :maxlength="16"
            @update:model-value="update('customer_postal_code', $event)"
        />
    </div>
</template>
