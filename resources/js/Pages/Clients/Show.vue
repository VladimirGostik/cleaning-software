<script setup lang="ts">
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';
import PlusIcon from '~icons/material-symbols/add-outline-rounded';
import TrashIcon from '~icons/material-symbols/delete-outline-rounded';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ClientFormDrawer from '@/Components/Clients/ClientFormDrawer.vue';
import ClientDetailCard from '@/Components/Clients/ClientDetailCard.vue';
import ContactsList from '@/Components/Contacts/ContactsList.vue';
import ClientObjectsTable from '@/Components/Clients/ClientObjectsTable.vue';
import RelatedContractsCard from '@/Components/Contracts/RelatedContractsCard.vue';
import RelatedQuotesCard from '@/Components/Quotes/RelatedQuotesCard.vue';
import ObjectFormDrawer from '@/Components/Objects/ObjectFormDrawer.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import type { Breadcrumb } from '@/types';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    client: App.Data.Clients.ClientDetailData;
    objects: App.Data.Objects.ObjectListItemData[];
    contracts: App.Data.Contracts.ContractListItemData[] | null;
    canCreateContract: boolean;
    quotes: App.Data.Quotes.QuoteListItemData[] | null;
    canCreateQuote: boolean;
}>();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('clients'), url: '/clients' },
    { label: props.client.name },
];

const ui = reactive({
    editOpen: false,
    objectOpen: false,
});

const { state, openModal, closeModal, confirmDelete, getModalTitle, getModalDescription } =
    useDeleteConfirm<App.Data.Clients.ClientDetailData>({
        resolveUrl: (c) => `/clients/${c.id}`,
        getTitle: () => t('client_delete'),
        getDescription: (c) => `${t('client_delete_confirm', { name: c.name })} ${t('client_delete_cascade_hint')}`,
    });
</script>

<template>
    <Header :title="client.name" :breadcrumbs="breadcrumbs">
        <template #actions>
            <button v-if="allows('edit clients')" type="button" class="btn btn-neutral" @click="ui.editOpen = true">
                <PencilSquareIcon />
                {{ t('edit') }}
            </button>

            <button v-if="allows('create objects')" type="button" class="btn btn-neutral" @click="ui.objectOpen = true">
                <PlusIcon />
                {{ t('client_object_add') }}
            </button>

            <button v-if="allows('delete clients')" type="button" class="btn btn-error" @click="openModal(client)">
                <TrashIcon />
                {{ t('delete') }}
            </button>
        </template>
    </Header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <ClientObjectsTable :objects="objects" />

            <RelatedContractsCard
                :contracts="contracts"
                :can-create="canCreateContract"
                :create-href="`/contracts/create?contractable_type=client&contractable_id=${client.id}&category=service_agreement`"
            />

            <RelatedQuotesCard :quotes="quotes" :can-create="canCreateQuote" :create-query="`client_id=${client.id}`" />

            <div class="card bg-base-100">
                <div class="card-body">
                    <h2 class="card-title">{{ t('note') }}</h2>
                    <p v-if="client.note" class="whitespace-pre-wrap">{{ client.note }}</p>
                    <p v-else class="text-muted">{{ t('client_no_note') }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <ClientDetailCard :client="client" />
            <ContactsList
                :contacts="client.contacts"
                :title="t('client_contacts')"
                :empty-label="t('client_no_contacts')"
            />
        </div>
    </div>

    <ClientFormDrawer :open="ui.editOpen" :client="client" @close="ui.editOpen = false" />

    <ObjectFormDrawer
        :open="ui.objectOpen"
        :clients="[{ id: client.id, name: client.name }]"
        @close="ui.objectOpen = false"
    />

    <ConfirmDeleteModal
        :is-open="state.isOpen"
        :title="getModalTitle()"
        :description="getModalDescription()"
        @cancel="closeModal"
        @confirm="confirmDelete"
    />
</template>
