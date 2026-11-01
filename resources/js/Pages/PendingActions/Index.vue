<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ShieldExclamationIcon from '~icons/material-symbols/gpp-maybe-outline-rounded';
import Header from '@/Layouts/Header.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import PendingActionRow from '@/Components/Ai/PendingActionRow.vue';
import { usePendingActionDecision } from '@/Composables/usePendingActionDecision';
import type { Breadcrumb } from '@/types';

defineProps<{
    pendingActions: App.Data.Ai.PendingActionData[];
}>();

const { t } = useI18n();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('pending_actions') }];

const { processingId, toConfirm, errorMessage, requestConfirm, cancel, confirmModal, dismissModal } =
    usePendingActionDecision();
</script>

<template>
    <Header :title="t('pending_actions')" :breadcrumbs="breadcrumbs" />

    <p class="mb-6 text-base-content/60">{{ t('pending_actions_intro') }}</p>

    <div v-if="errorMessage" class="alert alert-error mb-4" role="alert">{{ errorMessage }}</div>

    <EmptyState
        v-if="pendingActions.length === 0"
        :title="t('pending_actions_empty')"
        :description="t('pending_actions_empty_hint')"
        :icon="ShieldExclamationIcon"
    />

    <div v-else class="card bg-base-100 shadow-sm">
        <ul class="card-body divide-y divide-base-300">
            <PendingActionRow
                v-for="action in pendingActions"
                :key="action.id"
                :action="action"
                :processing="processingId === action.id"
                @confirm="requestConfirm(action)"
                @cancel="cancel(action)"
            />
        </ul>
    </div>

    <ConfirmDeleteModal
        :is-open="toConfirm !== null"
        :title="t('pending_action_confirm_title')"
        :description="toConfirm ? t('pending_action_confirm_description', { preview: toConfirm.preview }) : ''"
        :confirm-label="t('pending_action_confirm')"
        @cancel="dismissModal"
        @confirm="confirmModal"
    />
</template>
