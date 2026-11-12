<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import XMarkIcon from '~icons/material-symbols/close-outline-rounded';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        modal?: boolean;
    }>(),
    { modal: true },
);

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();
const titleId = useId();
const panel = ref<HTMLElement | null>(null);

const media = window.matchMedia('(min-width: 64rem)');
const wide = ref(media.matches);
const isModal = computed(() => props.modal || !wide.value);

let locked = false;
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

function syncWide(): void {
    wide.value = media.matches;
}

function onDocumentKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape' || event.defaultPrevented) return;
    if (document.querySelector('dialog[open], [aria-modal="true"]')) return;

    close();
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        if (!isModal.value) event.stopPropagation();
        close();
        return;
    }

    if (event.key !== 'Tab' || !isModal.value) return;

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

function lock(): void {
    locked = true;
    previouslyFocused = document.activeElement as HTMLElement | null;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    void nextTick(focusFirst);
}

function unlock(): void {
    if (!locked) return;

    locked = false;
    document.body.style.overflow = previousOverflow;
    previouslyFocused?.focus();
    previouslyFocused = null;
}

watch(
    () => props.open && isModal.value,
    (shouldLock) => (shouldLock ? lock() : unlock()),
    { immediate: true },
);

watch(
    () => props.open && !isModal.value,
    (listening) => {
        if (listening) document.addEventListener('keydown', onDocumentKeydown);
        else document.removeEventListener('keydown', onDocumentKeydown);
    },
    { immediate: true },
);

media.addEventListener('change', syncWide);

onUnmounted(() => {
    media.removeEventListener('change', syncWide);
    document.removeEventListener('keydown', onDocumentKeydown);
    if (locked) document.body.style.overflow = previousOverflow;
});
</script>

<template>
    <Teleport to="body">
        <template v-if="open">
            <div v-if="isModal" class="fixed inset-0 z-40 bg-base-content/40" aria-hidden="true" @click="close" />

            <aside
                ref="panel"
                role="dialog"
                :aria-modal="isModal"
                :aria-labelledby="titleId"
                tabindex="-1"
                class="kg-panel fixed inset-y-0 right-0 flex w-full max-w-2xl flex-col sm:rounded-l-card"
                :class="isModal ? 'z-50' : 'z-30'"
                @keydown="onKeydown"
            >
                <div class="sticky top-0 z-10 flex items-center justify-between bg-base-100 px-6 py-4">
                    <h2 :id="titleId" class="text-lg font-medium">{{ title }}</h2>
                    <button type="button" class="btn btn-square btn-ghost" :aria-label="t('close')" @click="close">
                        <XMarkIcon />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <slot />
                </div>
            </aside>
        </template>
    </Teleport>
</template>
