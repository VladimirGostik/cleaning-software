<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { popoverPosition } from '@/utils/popoverPosition';

defineOptions({ inheritAttrs: false });

const FOCUSABLE_SELECTOR =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])';

defineProps<{ block?: boolean }>();

const open = defineModel<boolean>('open', { default: false });

const anchor = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const position = ref({ top: 0, left: 0 });

function toggle(): void {
    open.value = !open.value;
}

function reposition(): void {
    if (!anchor.value || !panel.value) return;
    const rect = anchor.value.getBoundingClientRect();
    const size = { width: panel.value.offsetWidth, height: panel.value.offsetHeight };
    const { top, left } = popoverPosition(rect, size, { width: window.innerWidth, height: window.innerHeight });
    position.value = { top, left };
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
    event.stopPropagation();
    open.value = false;
}

function onPointerDown(event: Event): void {
    const target = event.target as Node;
    if (panel.value?.contains(target) || anchor.value?.contains(target)) return;
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
    <span ref="anchor" :class="block ? 'flex w-full' : 'inline-flex'" @click.stop>
        <slot name="trigger" :toggle="toggle" :open="open" />
    </span>

    <Teleport to="body">
        <div
            v-if="open"
            ref="panel"
            v-bind="$attrs"
            class="kg-float fixed z-50"
            :style="{ top: `${position.top}px`, left: `${position.left}px` }"
            @click.stop
        >
            <slot />
        </div>
    </Teleport>
</template>
