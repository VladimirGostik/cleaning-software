<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import { KG_ICONS } from '@/icons/kegosIcons';

defineProps<{
    canEdit: boolean;
    canDelete: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{ edit: []; delete: [] }>();

const { t } = useI18n();
const open = ref(false);
const menu = ref<HTMLElement | null>(null);

function onKeydown(event: KeyboardEvent): void {
    const list = Array.from(menu.value?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? []);
    const index = list.indexOf(document.activeElement as HTMLElement);
    const move: Record<string, number> = {
        ArrowDown: (index + 1) % list.length,
        ArrowUp: (index - 1 + list.length) % list.length,
        Home: 0,
        End: list.length - 1,
    };

    if (event.key in move) {
        event.preventDefault();
        list[move[event.key] as number]?.focus();
    }
}

function choose(action: 'edit' | 'delete'): void {
    open.value = false;

    if (action === 'edit') emit('edit');
    else emit('delete');
}
</script>

<template>
    <AnchoredPopover v-if="canEdit || canDelete" v-model:open="open" class="min-w-40 p-1">
        <template #trigger="{ toggle, open: isOpen }">
            <button
                type="button"
                class="btn btn-ghost btn-sm btn-square"
                :disabled="disabled"
                aria-haspopup="menu"
                :aria-expanded="isOpen"
                :aria-label="t('task_comment_actions')"
                :title="t('task_comment_actions')"
                @click="toggle"
            >
                <KG_ICONS.adminAction.more aria-hidden="true" />
            </button>
        </template>

        <div ref="menu" role="menu" :aria-label="t('task_comment_actions')" class="flex flex-col" @keydown="onKeydown">
            <button
                v-if="canEdit"
                type="button"
                role="menuitem"
                data-autofocus
                class="flex min-h-(--kg-menu-item-height) items-center rounded-tile-sm px-3 text-left hover:bg-base-100 focus-visible:bg-base-100"
                @click="choose('edit')"
            >
                {{ t('edit') }}
            </button>
            <button
                v-if="canDelete"
                type="button"
                role="menuitem"
                :data-autofocus="canEdit ? undefined : ''"
                class="flex min-h-(--kg-menu-item-height) items-center rounded-tile-sm px-3 text-left text-error hover:bg-base-100 focus-visible:bg-base-100"
                @click="choose('delete')"
            >
                {{ t('delete') }}
            </button>
        </div>
    </AnchoredPopover>
</template>
