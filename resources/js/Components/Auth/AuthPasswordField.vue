<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { LockClosedIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';
import { useFormContext } from '@/Components/Forms/useFormContext';
import { useFieldError, callValidate } from '@/Components/Forms/useFieldError';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    field: string;
    label: string;
    autocomplete?: string;
    required?: boolean;
}>();

const { t } = useI18n();
const id = useId();
const form = useFormContext();
const showPassword = ref(false);

const resolvedValue = computed(() => String((form as Record<string, unknown> | null)?.[props.field] ?? ''));
const resolvedError = useFieldError(props, form);

function onInput(event: Event) {
    const val = (event.target as HTMLInputElement).value;
    if (form) {
        (form as Record<string, unknown>)[props.field] = val;
    }
}

function onNativeChange() {
    callValidate(form, props.field);
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="id" class="text-sm font-medium text-slate-700">
            {{ label }}
        </label>
        <div class="relative">
            <LockClosedIcon
                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                aria-hidden="true"
            />
            <input
                :id="id"
                v-bind="$attrs"
                :value="resolvedValue"
                :type="showPassword ? 'text' : 'password'"
                :required="required"
                :autocomplete="autocomplete"
                :aria-invalid="resolvedError ? 'true' : undefined"
                :aria-describedby="resolvedError ? `${id}-error` : undefined"
                class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-10 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-100': resolvedError }"
                @input="onInput"
                @change="onNativeChange"
            />
            <button
                type="button"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600"
                :aria-label="showPassword ? t('auth_hide_password') : t('auth_show_password')"
                :aria-pressed="showPassword"
                @click="showPassword = !showPassword"
            >
                <EyeSlashIcon v-if="showPassword" class="h-4 w-4" />
                <EyeIcon v-else class="h-4 w-4" />
            </button>
        </div>
        <p v-if="resolvedError" :id="`${id}-error`" class="text-xs text-red-500">
            {{ resolvedError }}
        </p>
    </div>
</template>
