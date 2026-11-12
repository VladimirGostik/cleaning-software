<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ExclamationTriangleIcon from '~icons/material-symbols/warning-outline-rounded';
import NoSymbolIcon from '~icons/material-symbols/block-outline-rounded';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ObjectFormDrawer from '@/Components/Objects/ObjectFormDrawer.vue';
import ObjectDetailCard from '@/Components/Objects/ObjectDetailCard.vue';
import ObjectBillingCard from '@/Components/Objects/ObjectBillingCard.vue';
import ObjectAccessCard from '@/Components/Objects/ObjectAccessCard.vue';
import ObjectWorkBreakdownsCard from '@/Components/Objects/ObjectWorkBreakdownsCard.vue';
import RelatedContractsCard from '@/Components/Contracts/RelatedContractsCard.vue';
import RelatedQuotesCard from '@/Components/Quotes/RelatedQuotesCard.vue';
import ContactsList from '@/Components/Contacts/ContactsList.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import type { Breadcrumb } from '@/types';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    object: App.Data.Objects.ObjectDetailData;
    clients: App.Data.Clients.ClientOptionData[];
    workBreakdowns: App.Data.Schedule.WorkBreakdownDetailData[];
    contracts: App.Data.Contracts.ContractListItemData[] | null;
    canCreateContract: boolean;
    quotes: App.Data.Quotes.QuoteListItemData[] | null;
    canCreateQuote: boolean;
}>();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('objects'), url: '/objects' },
    { label: props.object.name },
];

const canEdit = computed(() => allows('edit objects') && props.clients.length > 0);

const page = usePage();
const focusSection =
    new URL(page.url, window.location.origin).searchParams.get('edit') === 'billing' ? 'billing' : undefined;

const ui = reactive({
    editOpen: focusSection === 'billing' && canEdit.value,
});

const reactivating = ref(false);

function reactivate(): void {
    router.post(`/objects/${props.object.id}/reactivate`, undefined, {
        preserveScroll: true,
        onStart: () => {
            reactivating.value = true;
        },
        onFinish: () => {
            reactivating.value = false;
        },
    });
}

const { state, openModal, closeModal, confirmDelete, getModalTitle, getModalDescription } =
    useDeleteConfirm<App.Data.Objects.ObjectDetailData>({
        method: 'post',
        resolveUrl: (o) => `/objects/${o.id}/deactivate`,
        getTitle: () => t('object_deactivate'),
        getDescription: (o) => `${t('object_deactivate_confirm', { name: o.name })} ${t('object_deactivate_hint')}`,
    });
</script>

<template>
    <Header :title="object.name" :breadcrumbs="breadcrumbs">
        <template #actions>
            <button v-if="canEdit" type="button" class="btn btn-neutral" @click="ui.editOpen = true">
                <PencilSquareIcon />
                {{ t('edit') }}
            </button>

            <button
                v-if="allows('delete objects') && object.is_active"
                type="button"
                class="btn btn-error"
                @click="openModal(object)"
            >
                <NoSymbolIcon />
                {{ t('object_deactivate') }}
            </button>
        </template>
    </Header>

    <div v-if="!object.is_active" class="alert alert-warning mb-4">
        <ExclamationTriangleIcon />
        <span>{{ t('object_inactive_banner') }}</span>
        <button
            v-if="canEdit"
            type="button"
            class="btn btn-sm btn-primary"
            :disabled="reactivating"
            @click="reactivate"
        >
            {{ t('object_reactivate') }}
        </button>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <ObjectAccessCard :object="object" />

            <div class="card bg-base-100">
                <div class="card-body">
                    <h2 class="card-title">{{ t('object_special_instructions') }}</h2>
                    <p v-if="object.special_instructions" class="whitespace-pre-wrap">
                        {{ object.special_instructions }}
                    </p>
                    <p v-else class="text-muted">{{ t('object_no_instructions') }}</p>
                </div>
            </div>

            <RelatedContractsCard
                :contracts="contracts"
                :can-create="canCreateContract"
                :create-href="`/contracts/create?contractable_type=cleaning_object&contractable_id=${object.id}&category=service_agreement`"
            />

            <RelatedQuotesCard
                :quotes="quotes"
                :can-create="canCreateQuote"
                :create-query="`cleaning_object_id=${object.id}`"
            />

            <ObjectWorkBreakdownsCard :breakdowns="workBreakdowns" />
        </div>

        <div class="space-y-4">
            <ObjectDetailCard :object="object" />

            <ObjectBillingCard v-if="object.billing !== null" :billing="object.billing" />

            <ContactsList
                v-if="object.contacts !== null"
                :contacts="object.contacts"
                :title="t('object_contacts')"
                :empty-label="t('object_no_contacts')"
            />
        </div>
    </div>

    <ObjectFormDrawer
        :open="ui.editOpen"
        :object="object"
        :clients="clients"
        :focus-section="focusSection"
        @close="ui.editOpen = false"
    />

    <ConfirmDeleteModal
        :is-open="state.isOpen"
        :title="getModalTitle()"
        :description="getModalDescription()"
        :confirm-label="t('object_deactivate')"
        @cancel="closeModal"
        @confirm="confirmDelete"
    />
</template>
