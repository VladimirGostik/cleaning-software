<script setup lang="ts">
import { nextTick, onUnmounted, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    open: boolean;
    title: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();
const titleId = useId();
const panel = ref<HTMLElement | null>(null);

let previouslyFocused: HTMLElement | null = null;
let previousOverflow = '';

const FOCUSABLE_SELECTOR =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function focusableElements(): HTMLElement[] {
    if (!panel.value) return [];
    return Array.from(panel.value.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR));
}

function focusFirst(): void {
    const el = panel.value;
    if (!el) return;

    const autofocusTarget = el.querySelector<HTMLElement>('[autofocus]');
    if (autofocusTarget) {
        autofocusTarget.focus();
        return;
    }

    const [first] = focusableElements();
    (first ?? el).focus();
}

function close(): void {
    emit('close');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
        return;
    }

    if (event.key !== 'Tab') return;

    const elements = focusableElements();
    if (elements.length === 0) return;

    const first = elements[0];
    const last = elements[elements.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            previouslyFocused = document.activeElement as HTMLElement | null;
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            void nextTick(focusFirst);
        } else {
            document.body.style.overflow = previousOverflow;
            previouslyFocused?.focus();
            previouslyFocused = null;
        }
    },
);

onUnmounted(() => {
    document.body.style.overflow = previousOverflow;
});
</script>

<template>
    <Teleport to="body">
        <template v-if="open">
            <div class="fixed inset-0 z-40 bg-neutral/40" aria-hidden="true" @click="close" />

            <aside
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
                tabindex="-1"
                class="fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col bg-base-100 shadow-xl"
                @keydown="onKeydown"
            >
                <div
                    class="sticky top-0 z-10 flex items-center justify-between border-b border-base-300 bg-base-100 px-6 py-4"
                >
                    <h2 :id="titleId" class="text-lg font-bold">{{ title }}</h2>
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm btn-circle"
                        :aria-label="t('close')"
                        @click="close"
                    >
                        <XMarkIcon class="size-4" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <slot />
                </div>
            </aside>
        </template>
    </Teleport>
</template>
