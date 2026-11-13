<script setup lang="ts">
import { inject, nextTick, onBeforeUnmount, provide, ref, watch } from 'vue';
import { POPOVER_CONTEXT, type PopoverNode } from '@/utils/popoverContext';
import { popoverPosition } from '@/utils/popoverPosition';

defineOptions({ inheritAttrs: false });

const FOCUSABLE_SELECTOR =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])';

defineProps<{ block?: boolean }>();

const open = defineModel<boolean>('open', { default: false });

const anchor = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const position = ref<{ top: number; left: number; maxHeight: number | null }>({ top: 0, left: 0, maxHeight: null });

const parent = inject(POPOVER_CONTEXT, null);
const children = new Set<PopoverNode>();

function isInside(node: Node): boolean {
    return (
        !!panel.value?.contains(node) ||
        !!anchor.value?.contains(node) ||
        [...children].some((child) => child.isInside(node))
    );
}

provide(POPOVER_CONTEXT, {
    register(node) {
        children.add(node);

        return () => children.delete(node);
    },
});

const unregister = parent?.register({ isInside, isOpen: () => open.value });

onBeforeUnmount(() => unregister?.());

function toggle(): void {
    open.value = !open.value;
}

function reposition(): void {
    if (!anchor.value || !panel.value) return;
    const rect = anchor.value.getBoundingClientRect();
    const size = { width: panel.value.offsetWidth, height: panel.value.scrollHeight };
    const { top, left, maxHeight } = popoverPosition(rect, size, {
        width: window.innerWidth,
        height: window.innerHeight,
    });
    position.value = { top, left, maxHeight };
}

function focusPanel(): void {
    const target =
        panel.value?.querySelector<HTMLElement>('[data-autofocus]') ??
        panel.value?.querySelector<HTMLElement>(FOCUSABLE_SELECTOR);
    target?.focus();
}

function focusTrigger(): void {
    anchor.value?.querySelector<HTMLElement>(FOCUSABLE_SELECTOR)?.focus();
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape') return;
    if ([...children].some((child) => child.isOpen())) return;
    event.stopPropagation();
    open.value = false;
}

function onPanelKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Tab' || !panel.value) return;
    const items = [...panel.value.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)];
    const edge = event.shiftKey ? items[0] : items[items.length - 1];
    if (items.length > 0 && document.activeElement !== edge) return;
    event.preventDefault();
    open.value = false;
}

function onFocusOut(event: { relatedTarget: unknown }): void {
    const next = event.relatedTarget as Node | null;
    if (!open.value || !next) return;
    if (isInside(next)) return;
    open.value = false;
}

function onPointerDown(event: Event): void {
    if (isInside(event.target as Node)) return;
    open.value = false;
}

function listen(): void {
    document.addEventListener('keydown', onKeydown, true);
    document.addEventListener('pointerdown', onPointerDown, true);
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);
}

function unlisten(): void {
    document.removeEventListener('keydown', onKeydown, true);
    document.removeEventListener('pointerdown', onPointerDown, true);
    window.removeEventListener('scroll', reposition, true);
    window.removeEventListener('resize', reposition);
}

watch(open, async (isOpen, wasOpen) => {
    if (isOpen) {
        listen();
        await nextTick();
        reposition();
        focusPanel();
        return;
    }

    unlisten();
    if (
        wasOpen &&
        (!document.activeElement ||
            document.activeElement === document.body ||
            panel.value?.contains(document.activeElement))
    ) {
        focusTrigger();
    }
});

onBeforeUnmount(unlisten);
</script>

<template>
    <span ref="anchor" :class="block ? 'flex w-full' : 'inline-flex'" @click.stop @focusout="onFocusOut">
        <slot name="trigger" :toggle="toggle" :open="open" />
    </span>

    <Teleport to="body">
        <div
            v-if="open"
            ref="panel"
            v-bind="$attrs"
            class="kg-float fixed z-(--kg-z-popover) overflow-y-auto"
            :style="{
                top: `${position.top}px`,
                left: `${position.left}px`,
                maxHeight: position.maxHeight === null ? undefined : `${position.maxHeight}px`,
            }"
            @click.stop
            @keydown="onPanelKeydown"
            @focusout="onFocusOut"
        >
            <slot />
        </div>
    </Teleport>
</template>
