<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { KG_ICONS } from '@/icons/kegosIcons';
import { taskStatusKey } from '@/utils/enums';

const props = defineProps<{
    column: App.Data.Tasks.TaskBoardColumnData;
    canCreate: boolean;
    dropTarget: boolean;
    showAllHref: string | null;
}>();

const emit = defineEmits<{
    create: [];
    dragenter: [];
    dragleave: [];
    drop: [];
}>();

const { t } = useI18n();

const canAdd = computed(() => props.canCreate && props.column.status !== 'done');
</script>

<template>
    <section
        class="flex w-[85vw] shrink-0 snap-start flex-col gap-2 self-start rounded-box bg-base-200 p-2 sm:w-80 lg:snap-none"
        :class="{ 'ring-2 ring-primary bg-primary-soft': dropTarget }"
        :aria-label="t(taskStatusKey(column.status))"
        @dragover.prevent
        @dragenter.prevent="emit('dragenter')"
        @dragleave="emit('dragleave')"
        @drop.prevent="emit('drop')"
    >
        <header class="flex items-center justify-between gap-2 px-1 py-1">
            <h2 class="font-semibold">{{ t(taskStatusKey(column.status)) }}</h2>
            <span class="text-sm tabular-nums text-base-content/60">{{ column.total }}</span>
        </header>

        <div class="flex flex-col gap-2">
            <slot />
            <p v-if="column.tasks.length === 0" class="px-1 py-4 text-center text-sm text-base-content/50">
                {{ t('task_board_column_empty') }}
            </p>
        </div>

        <footer v-if="canAdd || showAllHref" class="flex flex-col gap-1">
            <Link v-if="showAllHref" :href="showAllHref" class="btn btn-ghost btn-sm justify-start">
                {{ t('task_board_show_all') }}
            </Link>
            <button v-if="canAdd" type="button" class="btn btn-ghost btn-sm justify-start" @click="emit('create')">
                <KG_ICONS.adminAction.add class="size-4" aria-hidden="true" />
                {{ t('task_add') }}
            </button>
        </footer>
    </section>
</template>
