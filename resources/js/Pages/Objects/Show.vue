<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ExclamationTriangleIcon, NoSymbolIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ObjectFormDrawer from '@/Components/Objects/ObjectFormDrawer.vue';
import ObjectDetailCard from '@/Components/Objects/ObjectDetailCard.vue';
import ObjectAccessCard from '@/Components/Objects/ObjectAccessCard.vue';
import ObjectWorkBreakdownsCard from '@/Components/Objects/ObjectWorkBreakdownsCard.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import type { Breadcrumb } from '@/types';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    object: App.Data.Objects.ObjectDetailData;
    clients: App.Data.Clients.ClientOptionData[];
    workBreakdowns: App.Data.Schedule.WorkBreakdownDetailData[];
}>();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('objects'), url: '/objects' },
    { label: props.object.name },
];

const ui = reactive({
    editOpen: false,
});

const canEdit = computed(() => allows('edit objects') && props.clients.length > 0);

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
    <AppLayout>
        <Header :title="object.name" :breadcrumbs="breadcrumbs">
            <template #actions>
                <button v-if="canEdit" type="button" class="btn btn-ghost btn-sm" @click="ui.editOpen = true">
                    <PencilSquareIcon class="size-4" />
                    {{ t('edit') }}
                </button>

                <button
                    v-if="allows('delete objects') && object.is_active"
                    type="button"
                    class="btn btn-ghost btn-sm text-warning"
                    @click="openModal(object)"
                >
                    <NoSymbolIcon class="size-4" />
                    {{ t('object_deactivate') }}
                </button>
            </template>
        </Header>

        <div v-if="!object.is_active" class="alert alert-warning mb-6">
            <ExclamationTriangleIcon class="size-5" />
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

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <ObjectAccessCard :object="object" />

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('object_special_instructions') }}</h2>
                        <p v-if="object.special_instructions" class="whitespace-pre-wrap">
                            {{ object.special_instructions }}
                        </p>
                        <p v-else class="text-base-content/60">{{ t('object_no_instructions') }}</p>
                    </div>
                </div>

                <ObjectWorkBreakdownsCard :breakdowns="workBreakdowns" />
            </div>

            <div>
                <ObjectDetailCard :object="object" />
            </div>
        </div>

        <ObjectFormDrawer :open="ui.editOpen" :object="object" :clients="clients" @close="ui.editOpen = false" />

        <ConfirmDeleteModal
            :is-open="state.isOpen"
            :title="getModalTitle()"
            :description="getModalDescription()"
            :confirm-label="t('object_deactivate')"
            @cancel="closeModal"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
