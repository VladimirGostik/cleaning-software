<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ChevronUpDownIcon, PlusIcon } from '@heroicons/vue/24/outline';
import TenantColorDot from '@/Components/Tenants/TenantColorDot.vue';
import type { SharedTenant } from '@/types';

const props = defineProps<{ tenant: SharedTenant; compact?: boolean }>();

const emit = defineEmits<{
    'add-tenant': [];
}>();

const { t } = useI18n();

const switching = ref<string | null>(null);

function switchTo(id: string) {
    if (document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
    }
    router.post(
        `/tenants/${id}/switch`,
        {},
        {
            onStart: () => (switching.value = id),
            onFinish: () => (switching.value = null),
        },
    );
}
</script>

<template>
    <div class="dropdown w-full" :class="compact ? 'dropdown-end' : 'dropdown-bottom'">
        <div
            tabindex="0"
            role="button"
            aria-haspopup="menu"
            :aria-label="t('tenant_switch')"
            class="btn btn-sm btn-ghost text-neutral-content/70 hover:text-white hover:bg-white/5"
            :class="compact ? 'btn-square' : 'w-full justify-start gap-2'"
        >
            <TenantColorDot :color="props.tenant.active?.color ?? null" />
            <span v-if="!compact" class="truncate">{{ props.tenant.active?.name }}</span>
            <ChevronUpDownIcon class="size-4" :class="compact ? '' : 'ml-auto'" />
        </div>
        <ul
            tabindex="0"
            class="dropdown-content menu menu-sm bg-base-100 text-base-content rounded-box shadow-lg z-50 w-56 p-1"
        >
            <li v-for="item in props.tenant.available" :key="item.id">
                <button
                    type="button"
                    :class="{ active: item.id === props.tenant.active?.id }"
                    :aria-current="item.id === props.tenant.active?.id ? 'true' : undefined"
                    :disabled="switching !== null"
                    @click="switchTo(item.id)"
                >
                    <TenantColorDot :color="item.color" />
                    <span class="truncate">{{ item.name }}</span>
                    <span v-if="switching === item.id" class="loading loading-spinner loading-xs ml-auto" />
                </button>
            </li>
            <li class="menu-title" aria-hidden="true"><hr class="border-base-300" /></li>
            <li>
                <button type="button" @click="emit('add-tenant')">
                    <PlusIcon class="size-4" />
                    {{ t('nav_add_tenant') }}
                </button>
            </li>
        </ul>
    </div>
</template>
