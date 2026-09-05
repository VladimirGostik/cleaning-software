<script setup lang="ts">
    import { computed } from 'vue';
    import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = withDefaults(
        defineProps<{
            modelValue: App.Enums.QuoteKindEnum;
            options: SelectOption[];
            disabled?: boolean;
        }>(),
        {
            disabled: false,
        },
    );

    const emit = defineEmits<{
        'update:modelValue': [value: App.Enums.QuoteKindEnum];
    }>();

    const { t } = useTranslate();

    const radioOptions = computed<RadioOption[]>(() =>
        props.options.map((o) => ({ value: String(o.value), label: o.label, disabled: props.disabled })),
    );

    const hint = computed(() =>
        props.modelValue === 'itemized'
            ? t('quotes.form.kind_itemized_hint')
            : t('quotes.form.kind_document_hint'),
    );

    function onUpdate(value: string): void {
        emit('update:modelValue', value as App.Enums.QuoteKindEnum);
    }
</script>

<template>
    <div class="space-y-1">
        <RadioGroup
            :model-value="modelValue"
            :options="radioOptions"
            :label="t('quotes.form.kind')"
            @update:model-value="onUpdate"
        />
        <p class="text-xs text-base-content/60">{{ hint }}</p>
    </div>
</template>
