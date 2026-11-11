<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import OpenIcon from '~icons/material-symbols/open-in-new-rounded';
import CreatedIcon from '~icons/material-symbols/add-outline-rounded';
import UpdatedIcon from '~icons/material-symbols/edit-outline-rounded';
import { useAssistantChat } from '@/Composables/useAssistantChat';
import { usePageProps } from '@/Composables/usePageProps';
import { useTenantSwitchVisit } from '@/Composables/useTenantSwitchVisit';
import { isAllowedAssistantLink } from '@/utils/assistant';

defineProps<{ actions: readonly App.Data.Ai.AssistantActionData[] }>();

const { closeOnSmallScreen } = useAssistantChat();
const props = usePageProps();
const tenantSwitch = useTenantSwitchVisit();

function isOtherCompany(action: App.Data.Ai.AssistantActionData): boolean {
    return action.company_id !== null && action.company_id !== props.value.tenant.active?.id;
}

/** A record of another company opens after switching to it; a plain link would 404 under the open company. */
function openInCompany(action: App.Data.Ai.AssistantActionData): void {
    closeOnSmallScreen();
    tenantSwitch.navigate(action.company_id!, action.url!);
}

const ICONS: Record<App.Enums.AssistantActionTypeEnum, Component> = {
    open: OpenIcon,
    created: CreatedIcon,
    updated: UpdatedIcon,
};
</script>

<template>
    <ul v-if="actions.length > 0" class="mt-2 flex flex-wrap gap-2">
        <li v-for="(action, index) in actions" :key="index">
            <button
                v-if="isAllowedAssistantLink(action.url) && isOtherCompany(action)"
                type="button"
                class="badge badge-neutral gap-1"
                @click="openInCompany(action)"
            >
                <component :is="ICONS[action.type]" class="size-4" />
                {{ action.label }}
            </button>
            <Link
                v-else-if="isAllowedAssistantLink(action.url)"
                :href="action.url"
                class="badge badge-neutral gap-1"
                @click="closeOnSmallScreen"
            >
                <component :is="ICONS[action.type]" class="size-4" />
                {{ action.label }}
            </Link>
            <span v-else class="badge badge-neutral gap-1">
                <component :is="ICONS[action.type]" class="size-4" />
                {{ action.label }}
            </span>
        </li>
    </ul>
</template>
