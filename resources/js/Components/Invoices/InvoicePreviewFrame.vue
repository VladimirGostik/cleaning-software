<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const PAGE_WIDTH = 794;
const PAGE_HEIGHT = 1123;

const { t } = useI18n();

const host = ref<HTMLElement | null>(null);
const hostWidth = ref(PAGE_WIDTH);
let observer: InstanceType<typeof window.ResizeObserver> | null = null;

const scale = computed(() => Math.min(1, hostWidth.value / PAGE_WIDTH));

onMounted(() => {
    if (host.value === null) {
        return;
    }
    hostWidth.value = host.value.clientWidth;
    observer = new window.ResizeObserver(([entry]) => {
        hostWidth.value = entry.contentRect.width;
    });
    observer.observe(host.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div ref="host" class="overflow-hidden" :style="{ height: `${PAGE_HEIGHT * scale}px` }">
        <iframe
            src="/settings/invoicing/preview"
            :title="t('invoice_settings_template_preview')"
            sandbox="allow-same-origin"
            class="origin-top-left bg-base-100"
            :style="{ width: `${PAGE_WIDTH}px`, height: `${PAGE_HEIGHT}px`, transform: `scale(${scale})` }"
        />
    </div>
</template>
