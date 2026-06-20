<script setup lang="ts">
    import { computed, reactive } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import {
        PencilSquareIcon,
        DocumentArrowDownIcon,
        CheckCircleIcon,
        XCircleIcon,
    } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import Can from '@/Components/Can.vue';
    import ConfirmDialog from '@/Components/ConfirmDialog.vue';
    import ContractStatusBadge from '@/Components/Contracts/ContractStatusBadge.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useLocalizedDate } from '@/Composables/useLocalizedDate';

    interface Props {
        contract: App.Data.Contracts.ContractDetailData;
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);
    const { formatDate } = useLocalizedDate();

    const ui = reactive({
        signConfirmOpen: false,
        terminateConfirmOpen: false,
        terminateReason: '',
        deleteConfirmOpen: false,
    });

    function signContract(): void {
        router.post(
            `/contracts/${props.contract.id}/sign`,
            {},
            {
                onSuccess: () => {
                    ui.signConfirmOpen = false;
                },
            },
        );
    }

    function terminateContract(): void {
        router.post(
            `/contracts/${props.contract.id}/terminate`,
            {
                terminated_at: new Date().toISOString().slice(0, 10),
                termination_reason: ui.terminateReason || null,
            },
            {
                onSuccess: () => {
                    ui.terminateConfirmOpen = false;
                    ui.terminateReason = '';
                },
            },
        );
    }

    function deleteContract(): void {
        router.delete(`/contracts/${props.contract.id}`, {
            onSuccess: () => router.visit('/contracts'),
        });
    }
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/contracts">{{ t('contracts.title') }}</Link>
                </li>
                <li>{{ contract.title }}</li>
            </ul>
        </div>

        <PageHeader :title="contract.title">
            <template #badges>
                <ContractStatusBadge :status="contract.status" size="md" />
                <span class="badge badge-ghost">
                    {{ t('contract_category.' + contract.category) }}
                </span>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6">
            <!-- Left: document card -->
            <div class="flex flex-col gap-4">
                <!-- Meta grid -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <dt class="text-base-content/60">
                                    {{ t('contracts.col.valid_from') }}
                                </dt>
                                <dd class="font-medium mt-0.5">
                                    {{ formatDate(contract.valid_from) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-base-content/60">
                                    {{ t('contracts.col.end_date') }}
                                </dt>
                                <dd class="font-medium mt-0.5">
                                    {{
                                        contract.end_date
                                            ? formatDate(contract.end_date)
                                            : t('contracts.term_type.indefinite')
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-base-content/60">
                                    {{ t('contracts.col.term_type') }}
                                </dt>
                                <dd class="font-medium mt-0.5">
                                    {{ t('contract_term_type.' + contract.term_type) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-base-content/60">
                                    {{ t('contracts.col.category') }}
                                </dt>
                                <dd class="font-medium mt-0.5">
                                    {{ t('contract_category.' + contract.category) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Contractable section -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base mb-2">
                            {{
                                contract.category === 'employment'
                                    ? t('contracts.detail.employee')
                                    : t('contracts.detail.contractable')
                            }}
                        </h2>
                        <p class="font-medium">{{ contract.contractable_display_name }}</p>
                        <p v-if="contract.contract_template_name" class="text-sm text-base-content/60 mt-1">
                            {{ t('contracts.detail.template') }}: {{ contract.contract_template_name }}
                        </p>
                        <p v-if="contract.reference_number" class="text-sm text-base-content/60 mt-1">
                            {{ t('contracts.detail.reference_number') }}: {{ contract.reference_number }}
                        </p>
                    </div>
                </div>

                <!-- Employment fields (display only) -->
                <div
                    v-if="contract.category === 'employment' && contract.employment"
                    class="card bg-base-100 shadow-sm"
                >
                    <div class="card-body">
                        <h2 class="card-title text-base mb-2">
                            {{ t('contracts.detail.employment') }}
                        </h2>
                        <dl class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <div>
                                <dt class="text-base-content/60">
                                    {{ t('contracts.form.employment_type') }}
                                </dt>
                                <dd class="font-medium mt-0.5">
                                    {{ t('employment_type.' + contract.employment.employment_type) }}
                                </dd>
                            </div>
                            <div v-if="contract.employment.position">
                                <dt class="text-base-content/60">
                                    {{ t('contracts.form.employment_position') }}
                                </dt>
                                <dd class="font-medium mt-0.5">
                                    {{ contract.employment.position }}
                                </dd>
                            </div>
                            <div v-if="contract.employment.hourly_rate">
                                <dt class="text-base-content/60">
                                    {{ t('contracts.form.employment_hourly_rate') }}
                                </dt>
                                <dd class="font-medium mt-0.5">
                                    {{ contract.employment.hourly_rate }}
                                </dd>
                            </div>
                            <div v-if="contract.employment.monthly_salary">
                                <dt class="text-base-content/60">
                                    {{ t('contracts.form.employment_monthly_salary') }}
                                </dt>
                                <dd class="font-medium mt-0.5">
                                    {{ contract.employment.monthly_salary }}
                                </dd>
                            </div>
                            <div v-if="contract.employment.weekly_hours">
                                <dt class="text-base-content/60">
                                    {{ t('contracts.form.employment_weekly_hours') }}
                                </dt>
                                <dd class="font-medium mt-0.5">
                                    {{ contract.employment.weekly_hours }}
                                </dd>
                            </div>
                            <div v-if="contract.employment.probation_end_date">
                                <dt class="text-base-content/60">
                                    {{ t('contracts.form.employment_probation_end_date') }}
                                </dt>
                                <dd class="font-medium mt-0.5">
                                    {{ formatDate(contract.employment.probation_end_date) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Body -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base mb-2">{{ t('contracts.detail.body') }}</h2>
                        <pre class="whitespace-pre-wrap font-mono text-sm">{{ contract.body }}</pre>
                    </div>
                </div>

                <!-- Notes -->
                <div v-if="contract.notes" class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base mb-2">{{ t('contracts.detail.notes') }}</h2>
                        <p class="text-sm text-base-content/80 whitespace-pre-wrap">
                            {{ contract.notes }}
                        </p>
                    </div>
                </div>

                <!-- Termination info -->
                <div v-if="contract.terminated_at" class="card bg-base-100 shadow-sm border border-error/20">
                    <div class="card-body">
                        <h2 class="card-title text-base text-error mb-2">
                            {{ t('contracts.detail.terminated') }}
                        </h2>
                        <p class="text-sm text-base-content/70">
                            {{ formatDate(contract.terminated_at) }}
                        </p>
                        <p v-if="contract.termination_reason" class="text-sm mt-1">
                            {{ contract.termination_reason }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right: actions sidebar -->
            <div class="flex flex-col gap-2">
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body p-4">
                        <h3 class="font-semibold text-sm mb-3">{{ t('contracts.detail.actions') }}</h3>

                        <!-- Edit -->
                        <Can permission="edit contracts">
                            <a
                                v-if="contract.is_editable"
                                :href="`/contracts/${contract.id}/edit`"
                                class="btn btn-ghost btn-sm w-full justify-start mb-1"
                            >
                                <PencilSquareIcon class="w-4 h-4" />
                                {{ t('contracts.action.edit') }}
                            </a>
                        </Can>

                        <!-- Sign -->
                        <Can permission="edit contracts">
                            <button
                                v-if="contract.can_be_signed"
                                type="button"
                                class="btn btn-success btn-sm w-full justify-start mb-1"
                                @click="ui.signConfirmOpen = true"
                            >
                                <CheckCircleIcon class="w-4 h-4" />
                                {{ t('contracts.action.sign') }}
                            </button>
                        </Can>

                        <!-- Download PDF — always shown -->
                        <a
                            :href="`/contracts/${contract.id}/pdf`"
                            target="_blank"
                            class="btn btn-ghost btn-sm w-full justify-start mb-1"
                        >
                            <DocumentArrowDownIcon class="w-4 h-4" />
                            {{ t('contracts.action.download_pdf') }}
                        </a>

                        <!-- Terminate -->
                        <Can permission="terminate contracts">
                            <button
                                v-if="contract.can_be_terminated"
                                type="button"
                                class="btn btn-warning btn-sm w-full justify-start mb-1"
                                @click="ui.terminateConfirmOpen = true"
                            >
                                <XCircleIcon class="w-4 h-4" />
                                {{ t('contracts.action.terminate') }}
                            </button>
                        </Can>

                        <!-- Delete -->
                        <Can permission="delete contracts">
                            <button
                                v-if="contract.is_editable"
                                type="button"
                                class="btn btn-ghost btn-sm w-full justify-start text-error"
                                @click="ui.deleteConfirmOpen = true"
                            >
                                {{ t('contracts.action.delete') }}
                            </button>
                        </Can>
                    </div>
                </div>

                <!-- Signed at info -->
                <div v-if="contract.signed_at" class="card bg-base-100 shadow-sm">
                    <div class="card-body p-4 text-sm">
                        <p class="text-base-content/60">{{ t('contracts.detail.signed_at') }}</p>
                        <p class="font-medium mt-0.5">{{ formatDate(contract.signed_at) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sign confirm dialog -->
        <ConfirmDialog
            :open="ui.signConfirmOpen"
            :title="t('contracts.action.sign')"
            :body="t('contracts.action.sign_confirm')"
            :confirm-label="t('contracts.action.sign')"
            :cancel-label="t('common.cancel')"
            confirm-variant="primary"
            @confirm="signContract"
            @cancel="ui.signConfirmOpen = false"
        />

        <!-- Terminate dialog — custom inline (ConfirmDialog has no body slot) -->
        <dialog class="modal" :open="ui.terminateConfirmOpen">
            <div class="modal-box">
                <h3 class="font-bold text-lg">{{ t('contracts.action.terminate') }}</h3>
                <p class="py-2 text-base-content/70">{{ t('contracts.action.terminate_confirm') }}</p>
                <div class="mt-2">
                    <label class="fieldset-legend text-sm font-medium mb-1 block">
                        {{ t('contracts.action.terminate_reason') }}
                    </label>
                    <textarea
                        v-model="ui.terminateReason"
                        class="textarea textarea-bordered w-full"
                        :placeholder="t('contracts.action.terminate_reason_placeholder')"
                        :rows="3"
                    />
                </div>
                <div class="modal-action">
                    <button
                        type="button"
                        class="btn btn-ghost"
                        @click="
                            ui.terminateConfirmOpen = false;
                            ui.terminateReason = '';
                        "
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button type="button" class="btn btn-warning" @click="terminateContract">
                        {{ t('contracts.action.terminate') }}
                    </button>
                </div>
            </div>
            <div
                class="modal-backdrop"
                @click="
                    ui.terminateConfirmOpen = false;
                    ui.terminateReason = '';
                "
            />
        </dialog>

        <!-- Delete confirm dialog -->
        <ConfirmDialog
            :open="ui.deleteConfirmOpen"
            :title="t('contracts.action.delete')"
            :body="t('contracts.action.delete_confirm')"
            :confirm-label="t('contracts.action.delete')"
            :cancel-label="t('common.cancel')"
            confirm-variant="error"
            @confirm="deleteContract"
            @cancel="ui.deleteConfirmOpen = false"
        />
    </div>
</template>
