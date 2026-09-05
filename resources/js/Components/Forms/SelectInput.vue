<script setup lang="ts">
    import { computed, ref, nextTick, onBeforeUnmount, useId } from 'vue';
    import { CheckIcon } from '@heroicons/vue/24/outline';
    import FormField from './FormField.vue';
    import { useFormContext, callValidate } from './useFormContext';
    import { useFieldError } from './useFieldError';

    export interface SelectOption {
        value: string | number;
        label: string;
    }

    const props = withDefaults(
        defineProps<{
            field?: string;
            modelValue?: string | number;
            options: SelectOption[];
            label?: string;
            placeholder?: string;
            required?: boolean;
            disabled?: boolean;
            error?: string;
        }>(),
        {
            field: undefined,
            modelValue: undefined,
            label: undefined,
            placeholder: undefined,
            required: false,
            disabled: false,
            error: undefined,
        },
    );

    const emit = defineEmits<{
        'update:modelValue': [value: string | number];
    }>();

    const form = useFormContext();
    const resolvedError = useFieldError(props, form);

    const resolvedValue = computed<string | number>(() => {
        if (props.field && form) {
            return ((form as unknown as Record<string, unknown>)[props.field] as string | number) ?? '';
        }
        return props.modelValue ?? '';
    });

    const matchedOption = computed(() => props.options.find((o) => o.value === resolvedValue.value));
    const selectedLabel = computed(() => matchedOption.value?.label ?? '');
    const hasValue = computed(() => matchedOption.value !== undefined);

    // eslint-disable-next-line no-restricted-syntax -- template ref for click-outside hit-testing, imperative DOM access
    const rootRef = ref<HTMLElement | null>(null);
    // eslint-disable-next-line no-restricted-syntax -- template ref for focus restore on close, imperative DOM access
    const triggerRef = ref<HTMLButtonElement | null>(null);
    // eslint-disable-next-line no-restricted-syntax -- template ref for listbox focus + scrollIntoView, imperative DOM access
    const listRef = ref<HTMLUListElement | null>(null);

    // eslint-disable-next-line no-restricted-syntax -- imperative UI toggle: listbox open state, single-component
    const isOpen = ref(false);
    // eslint-disable-next-line no-restricted-syntax -- imperative roving-focus index for keyboard nav, single-component
    const activeIndex = ref(-1);

    // Stable ARIA ids
    const triggerId = useId();
    const listId = useId();

    function select(value: string | number): void {
        if (props.field && form) {
            (form as unknown as Record<string, unknown>)[props.field] = value;
            callValidate(form, props.field);
        }
        emit('update:modelValue', value);
        close();
    }

    function open(): void {
        if (props.disabled) {
            return;
        }
        isOpen.value = true;
        const currentIndex = props.options.findIndex((o) => o.value === resolvedValue.value);
        activeIndex.value = currentIndex >= 0 ? currentIndex : 0;
        document.addEventListener('pointerdown', onDocPointer);
        nextTick(() => {
            listRef.value?.focus();
            scrollActiveIntoView();
        });
    }

    function close(): void {
        isOpen.value = false;
        activeIndex.value = -1;
        document.removeEventListener('pointerdown', onDocPointer);
        if (triggerRef.value?.isConnected) {
            triggerRef.value.focus();
        }
    }

    function toggle(): void {
        if (isOpen.value) {
            close();
        } else {
            open();
        }
    }

    function onDocPointer(e: PointerEvent): void {
        if (!isOpen.value) {
            return;
        }
        if (rootRef.value && !rootRef.value.contains(e.target as Node)) {
            close();
        }
    }

    function scrollActiveIntoView(): void {
        if (!listRef.value || activeIndex.value < 0) {
            return;
        }
        const item = listRef.value.querySelector(
            `[id="${listId}-opt-${activeIndex.value}"]`,
        ) as HTMLElement | null;
        item?.scrollIntoView({ block: 'nearest' });
    }

    function onTriggerKeydown(e: KeyboardEvent): void {
        if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(e.key)) {
            e.preventDefault();
            open();
        }
    }

    function onListKeydown(e: KeyboardEvent): void {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex.value = Math.min(activeIndex.value + 1, props.options.length - 1);
            scrollActiveIntoView();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex.value = Math.max(activeIndex.value - 1, 0);
            scrollActiveIntoView();
        } else if (e.key === 'Home') {
            e.preventDefault();
            activeIndex.value = 0;
            scrollActiveIntoView();
        } else if (e.key === 'End') {
            e.preventDefault();
            activeIndex.value = props.options.length - 1;
            scrollActiveIntoView();
        } else if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            if (activeIndex.value >= 0) {
                select(props.options[activeIndex.value].value);
            }
        } else if (e.key === 'Escape') {
            close();
        } else if (e.key === 'Tab') {
            close();
        }
    }

    onBeforeUnmount(() => {
        document.removeEventListener('pointerdown', onDocPointer);
    });
</script>

<template>
    <FormField :label="label" :error="resolvedError" :required="required">
        <div ref="rootRef" class="relative w-full">
            <button
                :id="triggerId"
                ref="triggerRef"
                type="button"
                role="combobox"
                :aria-expanded="isOpen"
                aria-haspopup="listbox"
                :aria-controls="listId"
                :aria-required="required ? 'true' : undefined"
                :aria-invalid="resolvedError ? 'true' : undefined"
                :disabled="disabled"
                class="select select-bordered w-full flex items-center justify-between text-left"
                :class="{ 'select-error': resolvedError, 'text-base-content/50': !hasValue }"
                @click="toggle"
                @keydown="onTriggerKeydown"
            >
                <span class="truncate">{{ hasValue ? selectedLabel : (placeholder ?? '') }}</span>
            </button>

            <ul
                v-show="isOpen"
                :id="listId"
                ref="listRef"
                role="listbox"
                :aria-labelledby="triggerId"
                tabindex="-1"
                :aria-activedescendant="activeIndex >= 0 ? `${listId}-opt-${activeIndex}` : undefined"
                class="absolute z-50 mt-1 w-full max-h-60 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-lg py-1"
                @keydown="onListKeydown"
            >
                <li
                    v-for="(opt, i) in options"
                    :id="`${listId}-opt-${i}`"
                    :key="opt.value"
                    role="option"
                    :aria-selected="opt.value === resolvedValue"
                    class="flex items-center justify-between gap-2 px-3 py-2 cursor-pointer text-sm"
                    :class="{
                        'bg-base-200': i === activeIndex,
                        'font-medium': opt.value === resolvedValue,
                    }"
                    @click="select(opt.value)"
                    @mouseenter="activeIndex = i"
                >
                    <span class="truncate">{{ opt.label }}</span>
                    <CheckIcon v-if="opt.value === resolvedValue" class="size-4 shrink-0" />
                </li>
            </ul>
        </div>
    </FormField>
</template>
