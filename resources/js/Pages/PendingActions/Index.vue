<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ShieldExclamationIcon } from '@heroicons/vue/24/outline';
import Header from '@/Layouts/Header.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import PendingActionRow from '@/Components/Ai/PendingActionRow.vue';
import type { Breadcrumb } from '@/types';

defineProps<{
    pendingActions: App.Data.Ai.PendingActionData[];
}>();

const { t } = useI18n();

const DESTRUCTIVE: readonly App.Enums.PendingActionEnum[] = [
    'cancel_invoice',
    'delete_invoice_draft',
    'delete_quote',
    'delete_task',
    'delete_expense',
];

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('pending_actions') }];

const processingId = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const toConfirm = ref<App.Data.Ai.PendingActionData | null>(null);

function post(action: App.Data.Ai.PendingActionData, verb: 'confirm' | 'cancel'): void {
    router.post(`/pending-actions/${action.id}/${verb}`, undefined, {
        preserveScroll: true,
        onStart: () => {
            processingId.value = action.id;
            errorMessage.value = null;
        },
        onFinish: () => (processingId.value = null),
        onError: (errors: Record<string, string>) => {
            errorMessage.value = errors.status ?? Object.values(errors)[0] ?? t('expense_request_failed');
        },
    });
}

function requestConfirm(action: App.Data.Ai.PendingActionData): void {
    if (DESTRUCTIVE.includes(action.action)) {
        toConfirm.value = action;
        return;
    }
    post(action, 'confirm');
}

function confirmModal(): void {
    const action = toConfirm.value;
    toConfirm.value = null;
    if (action) post(action, 'confirm');
}
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
                @cancel="post(action, 'cancel')"
            />
        </ul>
    </div>

    <ConfirmDeleteModal
        :is-open="toConfirm !== null"
        :title="t('pending_action_confirm_title')"
        :description="toConfirm ? t('pending_action_confirm_description', { preview: toConfirm.preview }) : ''"
        :confirm-label="t('pending_action_confirm')"
        @cancel="toConfirm = null"
        @confirm="confirmModal"
    />
</template>
