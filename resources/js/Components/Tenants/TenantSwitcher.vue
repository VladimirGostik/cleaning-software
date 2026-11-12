<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ChevronUpDownIcon from '~icons/material-symbols/unfold-more-outline-rounded';
import CheckIcon from '~icons/material-symbols/check-outline-rounded';
import PlusIcon from '~icons/material-symbols/add-outline-rounded';
import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import TenantColorDot from '@/Components/Tenants/TenantColorDot.vue';
import { usePageProps } from '@/Composables/usePageProps';
import type { SharedTenant } from '@/types';

const props = defineProps<{ tenant: SharedTenant; compact?: boolean }>();

const emit = defineEmits<{
    'add-tenant': [];
}>();

const { t } = useI18n();
const pageProps = usePageProps();

const open = ref(false);
const switching = ref<string | null>(null);

function switchTo(id: string) {
    open.value = false;
    router.post(
        `/tenants/${id}/switch`,
        {},
        {
            onStart: () => (switching.value = id),
            onFinish: () => (switching.value = null),
        },
    );
}

function addTenant() {
    open.value = false;
    emit('add-tenant');
}
</script>

<template>
    <AnchoredPopover v-model:open="open" :block="!compact" class="w-56 p-1">
        <template #trigger="{ toggle, open: isOpen }">
            <button
                v-if="compact"
                type="button"
                class="btn btn-square btn-ghost text-base-content"
                aria-haspopup="menu"
                :aria-expanded="isOpen"
                :aria-label="t('tenant_switch')"
                @click="toggle"
            >
                <TenantColorDot :color="props.tenant.active?.color ?? null" />
            </button>
            <button
                v-else
                type="button"
                class="flex min-h-11 w-full items-center gap-2 rounded-tile bg-(--kg-control-bg) px-3 text-left text-sm font-medium text-base-content"
                aria-haspopup="menu"
                :aria-expanded="isOpen"
                :aria-label="t('tenant_switch')"
                @click="toggle"
            >
                <TenantColorDot :color="props.tenant.active?.color ?? null" />
                <span class="truncate">{{ props.tenant.active?.name }}</span>
                <ChevronUpDownIcon class="ml-auto text-muted" />
            </button>
        </template>

        <ul class="menu w-full p-0" role="menu">
            <li v-for="item in props.tenant.available" :key="item.id">
                <button
                    type="button"
                    role="menuitem"
                    :class="{ 'font-medium': item.id === props.tenant.active?.id }"
                    :aria-current="item.id === props.tenant.active?.id ? 'true' : undefined"
                    :disabled="switching !== null"
                    @click="switchTo(item.id)"
                >
                    <TenantColorDot :color="item.color" />
                    <span class="truncate">{{ item.name }}</span>
                    <span v-if="switching === item.id" class="loading loading-spinner loading-xs ml-auto" />
                    <CheckIcon v-else-if="item.id === props.tenant.active?.id" class="ml-auto" aria-hidden="true" />
                </button>
            </li>
            <li v-if="pageProps.canCreateTenant" class="mt-1">
                <button type="button" role="menuitem" @click="addTenant">
                    <PlusIcon />
                    {{ t('nav_add_tenant') }}
                </button>
            </li>
        </ul>
    </AnchoredPopover>
</template>
