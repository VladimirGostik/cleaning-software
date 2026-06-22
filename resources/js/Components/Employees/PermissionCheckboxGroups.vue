<script setup lang="ts">
    import FormField from '@/Components/Forms/FormField.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    export interface PermissionItem {
        value: string;
        label: string;
    }

    export interface PermissionGroup {
        module: string;
        permissions: PermissionItem[];
    }

    const props = defineProps<{
        modelValue: string[];
        groups: PermissionGroup[];
        error?: string;
    }>();

    const emit = defineEmits<{
        'update:modelValue': [string[]];
    }>();

    const { t } = useTranslate();

    function isChecked(value: string): boolean {
        return props.modelValue.includes(value);
    }

    function toggle(value: string): void {
        const next = isChecked(value)
            ? props.modelValue.filter((v) => v !== value)
            : [...props.modelValue, value];
        emit('update:modelValue', next);
    }
</script>

<template>
    <FormField :label="t('employees.form.section.permissions')" :error="error">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-1">
            <div v-for="group in groups" :key="group.module">
                <p class="text-sm font-medium text-base-content/80 mb-2">
                    {{ t('permission_group.' + group.module) }}
                </p>
                <div class="flex flex-col gap-1">
                    <label
                        v-for="perm in group.permissions"
                        :key="perm.value"
                        class="label cursor-pointer justify-start gap-2 p-0"
                    >
                        <input
                            type="checkbox"
                            class="checkbox checkbox-sm"
                            :checked="isChecked(perm.value)"
                            :value="perm.value"
                            @change="toggle(perm.value)"
                        />
                        <span class="label-text text-sm">{{ perm.label }}</span>
                    </label>
                </div>
            </div>
        </div>
    </FormField>
</template>
