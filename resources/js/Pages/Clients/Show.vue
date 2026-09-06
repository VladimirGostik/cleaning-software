<script setup lang="ts">
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ClientFormDrawer from '@/Components/Clients/ClientFormDrawer.vue';
import ClientDetailCard from '@/Components/Clients/ClientDetailCard.vue';
import ClientContactsList from '@/Components/Clients/ClientContactsList.vue';
import ClientObjectsTable from '@/Components/Clients/ClientObjectsTable.vue';
import ObjectFormDrawer from '@/Components/Objects/ObjectFormDrawer.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import type { Breadcrumb } from '@/types';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    client: App.Data.Clients.ClientDetailData;
    objects: App.Data.Objects.ObjectListItemData[];
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
    <AppLayout>
        <Header :title="client.name" :breadcrumbs="breadcrumbs">
            <template #actions>
                <button
                    v-if="allows('edit clients')"
                    type="button"
                    class="btn btn-ghost btn-sm"
                    @click="ui.editOpen = true"
                >
                    <PencilSquareIcon class="size-4" />
                    {{ t('edit') }}
                </button>

                <button
                    v-if="allows('create objects')"
                    type="button"
                    class="btn btn-ghost btn-sm"
                    @click="ui.objectOpen = true"
                >
                    <PlusIcon class="size-4" />
                    {{ t('client_object_add') }}
                </button>

                <button
                    v-if="allows('delete clients')"
                    type="button"
                    class="btn btn-ghost btn-sm text-error"
                    @click="openModal(client)"
                >
                    <TrashIcon class="size-4" />
                    {{ t('delete') }}
                </button>
            </template>
        </Header>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <ClientObjectsTable :objects="objects" />

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('note') }}</h2>
                        <p v-if="client.note" class="whitespace-pre-wrap">{{ client.note }}</p>
                        <p v-else class="text-base-content/60">{{ t('client_no_note') }}</p>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <ClientDetailCard :client="client" />
                <ClientContactsList :contacts="client.contacts" />
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
    </AppLayout>
</template>
