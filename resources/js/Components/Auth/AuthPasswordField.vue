<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import LockClosedIcon from '~icons/material-symbols/lock-outline-rounded';
import EyeIcon from '~icons/material-symbols/visibility-outline-rounded';
import EyeSlashIcon from '~icons/material-symbols/visibility-off-outline-rounded';
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
    <div class="flex flex-col gap-1">
        <label :for="id" class="text-xs text-muted">
            {{ label }}
        </label>
        <div class="relative">
            <LockClosedIcon class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" aria-hidden="true" />
            <input
                :id="id"
                v-bind="$attrs"
                :value="resolvedValue"
                :type="showPassword ? 'text' : 'password'"
                :required="required"
                :autocomplete="autocomplete"
                :aria-invalid="resolvedError ? 'true' : undefined"
                :aria-describedby="resolvedError ? `${id}-error` : undefined"
                class="w-full rounded-field py-2.5 pl-10 pr-12 text-sm text-base-content placeholder:text-muted outline-none transition auth-input max-lg:text-base"
                :class="{ 'auth-input-error': resolvedError }"
                @input="onInput"
                @change="onNativeChange"
            />
            <button
                type="button"
                class="btn btn-ghost btn-square absolute right-1 top-1/2 -translate-y-1/2 text-muted hover:text-base-content"
                :aria-label="showPassword ? t('auth_hide_password') : t('auth_show_password')"
                :aria-pressed="showPassword"
                @click="showPassword = !showPassword"
            >
                <EyeSlashIcon v-if="showPassword" />
                <EyeIcon v-else />
            </button>
        </div>
        <p v-if="resolvedError" :id="`${id}-error`" class="text-xs text-error">
            {{ resolvedError }}
        </p>
    </div>
</template>
