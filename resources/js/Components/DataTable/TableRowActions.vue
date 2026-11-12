<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import { KG_ICONS } from '@/icons/kegosIcons';

const props = defineProps<{
    editHref?: string | null;
    deleteHref?: string | null;
}>();

const confirmOpen = ref(false);

function confirmDelete(): void {
    if (!props.deleteHref) return;
    confirmOpen.value = false;
    router.delete(props.deleteHref, { preserveScroll: true });
}
</script>

<template>
    <div class="flex items-center justify-end gap-1">
        <slot />

        <Link
            v-if="editHref"
            :href="editHref"
            class="btn btn-ghost btn-sm btn-square"
            :title="$t('edit')"
            :aria-label="$t('edit')"
        >
            <KG_ICONS.adminAction.edit />
        </Link>

        <AnchoredPopover v-if="deleteHref" v-model:open="confirmOpen" class="flex w-48 flex-col gap-2 p-3">
            <template #trigger="{ toggle, open }">
                <button
                    type="button"
                    class="btn btn-ghost btn-sm btn-square text-error"
                    :title="$t('delete')"
                    :aria-label="$t('delete')"
                    aria-haspopup="true"
                    :aria-expanded="open"
                    @click="toggle"
                >
                    <KG_ICONS.adminAction.delete />
                </button>
            </template>

            <p class="text-center text-sm font-medium">{{ $t('confirm_delete') }}</p>
            <div class="flex gap-2">
                <button type="button" class="btn btn-ghost btn-sm flex-1" @click="confirmOpen = false">
                    {{ $t('no') }}
                </button>
                <button type="button" class="btn btn-error btn-sm flex-1" @click="confirmDelete">
                    {{ $t('yes') }}
                </button>
            </div>
        </AnchoredPopover>
    </div>
</template>
