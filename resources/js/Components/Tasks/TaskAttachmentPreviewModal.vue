<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ArrowDownTrayIcon, ChevronLeftIcon, ChevronRightIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { formatDatetime } from '@/utils/date';

const props = defineProps<{
    images: App.Data.Tasks.TaskAttachmentData[];
    activeId: string | null;
}>();

const emit = defineEmits<{ 'update:activeId': [id: string | null] }>();

const { t } = useI18n();

const dialog = useTemplateRef<HTMLDialogElement>('dialog');
const loaded = ref(false);
const failed = ref(false);

const index = computed(() => props.images.findIndex((image) => image.id === props.activeId));
const active = computed(() => (index.value > -1 ? props.images[index.value] : null));
const hasSiblings = computed(() => props.images.length > 1);

watch(
    () => props.activeId,
    (id) => {
        loaded.value = false;
        failed.value = false;

        if (id !== null && dialog.value && !dialog.value.open) {
            dialog.value.showModal();
        } else if (id === null && dialog.value?.open) {
            dialog.value.close();
        }
    },
    { flush: 'post' },
);

function step(direction: 1 | -1): void {
    if (!hasSiblings.value) {
        return;
    }

    const next = (index.value + direction + props.images.length) % props.images.length;

    emit('update:activeId', props.images[next].id);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowLeft') {
        step(-1);
    } else if (event.key === 'ArrowRight') {
        step(1);
    }
}
</script>

<template>
    <dialog
        ref="dialog"
        class="modal"
        :aria-label="active?.name"
        @close="emit('update:activeId', null)"
        @keydown="onKeydown"
    >
        <div v-if="active" class="modal-box flex max-h-[92vh] w-full max-w-4xl flex-col gap-3 p-4">
            <div class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ active.name }}</p>
                    <p class="text-xs text-base-content/60">
                        <template v-if="active.uploaded_by_name">
                            {{ t('task_attachment_uploaded_by', { name: active.uploaded_by_name }) }} ·
                        </template>
                        {{ formatDatetime(active.created_at) }}
                    </p>
                </div>
                <a
                    :href="active.download_url"
                    download
                    class="btn btn-ghost btn-sm btn-square"
                    :aria-label="t('task_attachment_download')"
                    :title="t('task_attachment_download')"
                >
                    <ArrowDownTrayIcon class="size-4" />
                </a>
                <button
                    type="button"
                    class="btn btn-ghost btn-sm btn-square"
                    :aria-label="t('close')"
                    :title="t('close')"
                    @click="emit('update:activeId', null)"
                >
                    <XMarkIcon class="size-4" />
                </button>
            </div>

            <div
                class="relative flex min-h-48 flex-1 items-center justify-center overflow-hidden rounded-box bg-base-200"
            >
                <span v-if="!loaded && !failed" class="loading loading-spinner" :aria-label="t('loading')" />
                <p v-if="failed" class="p-6 text-sm text-base-content/60">{{ t('no_preview') }}</p>
                <img
                    v-else
                    :key="active.id"
                    :src="active.download_url"
                    :alt="active.name"
                    class="max-h-[70vh] max-w-full object-contain"
                    :class="{ invisible: !loaded, absolute: !loaded }"
                    @load="loaded = true"
                    @error="failed = true"
                />

                <template v-if="hasSiblings">
                    <button
                        type="button"
                        class="btn btn-circle btn-sm absolute left-2 top-1/2 -translate-y-1/2"
                        :aria-label="t('previous')"
                        @click="step(-1)"
                    >
                        <ChevronLeftIcon class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="btn btn-circle btn-sm absolute right-2 top-1/2 -translate-y-1/2"
                        :aria-label="t('next')"
                        @click="step(1)"
                    >
                        <ChevronRightIcon class="size-4" />
                    </button>
                </template>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button :aria-label="t('close')">{{ t('close') }}</button>
        </form>
    </dialog>
</template>
