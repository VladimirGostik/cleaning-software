<script setup lang="ts">
    import { useTranslate } from '@/Composables/useTranslate';
    import type { TenantColorOption } from '@/types';

    const props = defineProps<{
        modelValue: App.Enums.TenantColorEnum | null;
        colors: TenantColorOption[];
        label?: string;
        error?: string;
        required?: boolean;
    }>();

    const emit = defineEmits<{
        (e: 'update:modelValue', value: App.Enums.TenantColorEnum | null): void;
    }>();

    const { t } = useTranslate();

    function select(value: App.Enums.TenantColorEnum) {
        emit('update:modelValue', props.modelValue === value ? null : value);
    }
</script>

<template>
    <fieldset class="fieldset w-full">
        <legend v-if="label" class="fieldset-legend">
            {{ label }}
            <span v-if="required" class="text-error">*</span>
        </legend>
        <div class="flex flex-wrap gap-2 pt-1" role="group" :aria-label="label ?? t('tenant.add.color')">
            <button
                v-for="opt in colors"
                :key="opt.value"
                type="button"
                class="h-8 w-8 rounded-full border-2 transition focus:outline-none focus:ring-2 focus:ring-offset-1"
                :style="{ backgroundColor: opt.value }"
                :aria-label="opt.label"
                :aria-pressed="modelValue === opt.value"
                :class="
                    modelValue === opt.value
                        ? 'border-slate-900 ring-2 ring-slate-900 ring-offset-1'
                        : 'border-transparent hover:border-slate-400'
                "
                @click="select(opt.value as App.Enums.TenantColorEnum)"
            />
        </div>
        <span v-if="error" class="fieldset-label text-error">{{ error }}</span>
    </fieldset>
</template>
