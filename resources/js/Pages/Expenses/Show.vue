<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import FileInput from '@/Components/Forms/FileInput.vue';
import ExpenseStatusBadge from '@/Components/Expenses/ExpenseStatusBadge.vue';
import ExpenseCategoryBadge from '@/Components/Expenses/ExpenseCategoryBadge.vue';
import ExpenseProposalHint from '@/Components/Expenses/ExpenseProposalHint.vue';
import ExpenseFormDrawer from '@/Components/Expenses/ExpenseFormDrawer.vue';
import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { formatDate, formatDatetime } from '@/utils/date';
import { EXPENSE_CATEGORIES, expenseCategoryKey, expenseSourceKey } from '@/utils/enums';
import type { Breadcrumb } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    expense: App.Data.Expenses.ExpenseDetailData;
    context: App.Data.Expenses.ExpenseFormContextData;
}>();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('expenses'), url: '/expenses' },
    { label: formatDate(props.expense.date) },
]);

const ui = reactive({ editOpen: false });

const confirmForm = useForm<{ category: string }>('post', `/expenses/${props.expense.id}/confirm`, {
    category: props.expense.category ?? props.expense.proposed_category ?? '',
});

const receiptForm = useForm<{ receipt: File | null }>('post', `/expenses/${props.expense.id}/receipt`, {
    receipt: null,
});

const categoryOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('expense_category_none') },
    ...EXPENSE_CATEGORIES.map((v) => ({ value: v, label: t(expenseCategoryKey(v)) })),
]);

function submitConfirm(): void {
    confirmForm.submit({ preserveScroll: true });
}

function submitReceipt(): void {
    receiptForm.submit({
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => receiptForm.reset(),
    });
}

const { state, openModal, closeModal, confirmDelete, getModalTitle, getModalDescription } =
    useDeleteConfirm<App.Data.Expenses.ExpenseDetailData>({
        resolveUrl: (e) => `/expenses/${e.id}`,
        getTitle: () => t('expense_delete'),
        getDescription: () => t('expense_delete_confirm'),
    });
</script>

<template>
    <Header :title="expense.supplier_name ?? formatDate(expense.date)" :breadcrumbs="breadcrumbs">
        <template #actions>
            <button v-if="expense.can.update" type="button" class="btn btn-ghost btn-sm" @click="ui.editOpen = true">
                <PencilSquareIcon class="size-4" />
                {{ t('edit') }}
            </button>

            <button
                v-if="expense.can.delete"
                type="button"
                class="btn btn-ghost btn-sm text-error"
                @click="openModal(expense)"
            >
                <TrashIcon class="size-4" />
                {{ t('delete') }}
            </button>
        </template>
    </Header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('description') }}</h2>
                    <p v-if="expense.description" class="whitespace-pre-wrap">{{ expense.description }}</p>
                    <p v-else class="text-base-content/60">{{ t('empty_dash') }}</p>
                </div>
            </div>

            <ExpenseProposalHint
                v-if="expense.ai_proposal && expense.status === 'draft'"
                :proposal="expense.ai_proposal"
                :companies="context.companies"
            />

            <div v-if="expense.can.confirm" class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <form novalidate class="flex items-end gap-2" @submit.prevent="submitConfirm">
                        <div class="flex-1">
                            <SelectInput
                                v-model="confirmForm.category"
                                :label="t('expense_category')"
                                :options="categoryOptions"
                                :error="confirmForm.errors.category ?? confirmForm.errors.status"
                                required
                            />
                        </div>
                        <button
                            type="submit"
                            class="btn btn-primary btn-sm"
                            :disabled="confirmForm.processing || confirmForm.category === ''"
                        >
                            {{ t('expense_confirm') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('expense_details') }}</h2>

                    <div class="flex flex-wrap gap-2">
                        <ExpenseStatusBadge :status="expense.status" />
                        <ExpenseCategoryBadge v-if="expense.category" :category="expense.category" />
                    </div>

                    <dl class="mt-2 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('expense_company') }}</dt>
                            <dd>{{ expense.tenant_name ?? t('empty_dash') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('expense_date') }}</dt>
                            <dd>{{ formatDate(expense.date) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('expense_amount') }}</dt>
                            <dd>{{ expense.amount }} {{ expense.currency }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('expense_vat_amount') }}</dt>
                            <dd>{{ expense.vat_amount ?? t('empty_dash') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('expense_supplier') }}</dt>
                            <dd>{{ expense.supplier_name ?? t('empty_dash') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('expense_source') }}</dt>
                            <dd>{{ t(expenseSourceKey(expense.source)) }}</dd>
                        </div>
                        <div v-if="expense.confirmed_at" class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('expense_confirmed_at') }}</dt>
                            <dd>{{ formatDatetime(expense.confirmed_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('expense_receipt') }}</h2>

                    <a v-if="expense.receipt" :href="expense.receipt.download_url" class="link" target="_blank">
                        {{ expense.receipt.file_name }}
                    </a>
                    <p v-else class="text-sm text-base-content/60">{{ t('expense_receipt_none') }}</p>

                    <form v-if="expense.can.update" novalidate class="space-y-2" @submit.prevent="submitReceipt">
                        <FileInput
                            :label="t('expense_receipt_attach')"
                            accept="application/pdf,image/jpeg,image/png,image/webp"
                            :error="receiptForm.errors.receipt"
                            @update:model-value="receiptForm.receipt = $event"
                        />
                        <button
                            type="submit"
                            class="btn btn-primary btn-sm"
                            :disabled="receiptForm.processing || !receiptForm.receipt"
                        >
                            {{ t('expense_receipt_attach') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <ExpenseFormDrawer
        v-if="expense.can.update"
        :open="ui.editOpen"
        :context="context"
        :expense="expense"
        @close="ui.editOpen = false"
    />

    <ConfirmDeleteModal
        :is-open="state.isOpen"
        :title="getModalTitle()"
        :description="getModalDescription()"
        @cancel="closeModal"
        @confirm="confirmDelete"
    />
</template>
