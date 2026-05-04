<script setup lang="ts">
    import { reactive, computed } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import {
        PencilSquareIcon,
        TrashIcon,
        DocumentTextIcon,
        BuildingOffice2Icon,
        EnvelopeIcon,
        PhoneIcon,
        MapPinIcon,
        IdentificationIcon,
        EllipsisVerticalIcon,
        UsersIcon,
        DocumentDuplicateIcon,
        ReceiptPercentIcon,
    } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';
    import PageHeader from '@/Components/PageHeader.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import ClientTypeBadge from '@/Components/Clients/ClientTypeBadge.vue';
    import ClientFormDrawer from '@/Components/Clients/ClientFormDrawer.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';

    interface Props {
        client: App.Data.Clients.ClientDetailData;
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const can = computed(() => pageProps.can ?? {});
    const flash = computed(() => pageProps.flash);

    const ui = reactive({ editDrawerOpen: false, deleteConfirmOpen: false });

    function formatDate(dateStr: string): string {
        return new Date(dateStr).toLocaleDateString('sk-SK', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

    const subtitle = computed(() =>
        t('clients.detail.customer_since').replace('{date}', formatDate(props.client.created_at)),
    );

    function onDrawerSaved() {
        ui.editDrawerOpen = false;
        router.reload({ only: ['client'] });
    }

    function confirmDelete() {
        router.delete(`/clients/${props.client.id}`, {
            onSuccess: () => router.visit('/clients'),
        });
    }
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto">
            <div v-if="flash.success" class="alert alert-success mb-4">
                <span>{{ flash.success }}</span>
            </div>

            <!-- Breadcrumb -->
            <div class="breadcrumbs text-sm mb-4">
                <ul>
                    <li>
                        <Link href="/clients">{{ t('clients.title') }}</Link>
                    </li>
                    <li>{{ client.name }}</li>
                </ul>
            </div>

            <PageHeader :title="client.name" :subtitle="subtitle">
                <template #badges>
                    <ClientTypeBadge :type="client.type" />
                    <span v-if="client.is_vat_payer" class="badge badge-outline">
                        {{ t('clients.form.is_vat_payer') }}
                    </span>
                </template>
                <template #actions>
                    <button
                        v-if="can.editClients"
                        type="button"
                        class="btn btn-primary"
                        @click="ui.editDrawerOpen = true"
                    >
                        <PencilSquareIcon class="w-4 h-4" />
                        {{ t('clients.edit') }}
                    </button>

                    <button type="button" class="btn btn-ghost" disabled>
                        <DocumentTextIcon class="w-4 h-4" />
                        {{ t('clients.detail.create_quote') }}
                    </button>

                    <button type="button" class="btn btn-ghost" disabled>
                        <BuildingOffice2Icon class="w-4 h-4" />
                        {{ t('clients.detail.add_object') }}
                    </button>

                    <div v-if="can.deleteClients" class="dropdown dropdown-end">
                        <div tabindex="0" role="button" class="btn btn-ghost btn-square">
                            <EllipsisVerticalIcon class="w-5 h-5" />
                        </div>
                        <ul
                            tabindex="0"
                            class="dropdown-content menu bg-base-100 rounded-box z-10 w-40 p-2 shadow"
                        >
                            <li>
                                <button
                                    type="button"
                                    class="text-error"
                                    @click="ui.deleteConfirmOpen = true"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                    {{ t('clients.delete') }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </template>
            </PageHeader>

            <!-- Main grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Left: 2/3 -->
                <div class="md:col-span-2 space-y-6">
                    <!-- Objekty -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title">{{ t('clients.detail.objects') }}</h2>
                            <EmptyState
                                :title="t('clients.detail.no_objects')"
                                :icon="BuildingOffice2Icon"
                            />
                        </div>
                    </div>

                    <!-- Zmluvy -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title">{{ t('clients.detail.contracts') }}</h2>
                            <EmptyState
                                :title="t('clients.detail.no_contracts')"
                                :icon="DocumentDuplicateIcon"
                            />
                        </div>
                    </div>

                    <!-- Faktury -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title">{{ t('clients.detail.invoices') }}</h2>
                            <EmptyState
                                :title="t('clients.detail.no_invoices')"
                                :icon="ReceiptPercentIcon"
                            />
                        </div>
                    </div>
                </div>

                <!-- Right: 1/3 -->
                <div class="space-y-6">
                    <!-- Kontaktne udaje -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('clients.detail.contact') }}</h2>
                            <dl class="space-y-2 text-sm mt-2">
                                <div v-if="client.email" class="flex items-center gap-2">
                                    <EnvelopeIcon class="w-4 h-4 text-base-content/50 shrink-0" />
                                    <a :href="`mailto:${client.email}`" class="link link-hover">
                                        {{ client.email }}
                                    </a>
                                </div>
                                <div v-if="client.phone" class="flex items-center gap-2">
                                    <PhoneIcon class="w-4 h-4 text-base-content/50 shrink-0" />
                                    <a :href="`tel:${client.phone}`" class="link link-hover">
                                        {{ client.phone }}
                                    </a>
                                </div>
                                <div
                                    v-if="client.street || client.city || client.postal_code"
                                    class="flex items-start gap-2"
                                >
                                    <MapPinIcon class="w-4 h-4 text-base-content/50 shrink-0 mt-0.5" />
                                    <address class="not-italic">
                                        <span v-if="client.street">{{ client.street }}<br /></span>
                                        <span v-if="client.city || client.postal_code">
                                            {{ client.postal_code }} {{ client.city }}<br />
                                        </span>
                                        <span v-if="client.country">{{ client.country }}</span>
                                    </address>
                                </div>
                                <div v-if="client.ico" class="flex items-center gap-2">
                                    <IdentificationIcon class="w-4 h-4 text-base-content/50 shrink-0" />
                                    <span>{{ t('clients.form.ico') }}: {{ client.ico }}</span>
                                </div>
                                <div v-if="client.dic" class="flex items-center gap-2">
                                    <IdentificationIcon class="w-4 h-4 text-base-content/50 shrink-0" />
                                    <span>{{ t('clients.form.dic') }}: {{ client.dic }}</span>
                                </div>
                                <div v-if="client.vat_number" class="flex items-center gap-2">
                                    <IdentificationIcon class="w-4 h-4 text-base-content/50 shrink-0" />
                                    <span>{{ t('clients.form.ic_dph') }}: {{ client.vat_number }}</span>
                                </div>
                                <div class="flex items-center gap-2 pt-1">
                                    <span class="text-base-content/60">{{ t('clients.form.is_vat_payer') }}:</span>
                                    <span>{{ client.is_vat_payer ? t('common.yes') : t('common.no') }}</span>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Kontaktne osoby -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('clients.detail.contact_persons') }}</h2>
                            <div v-if="client.contacts.length > 0" class="space-y-3 mt-2">
                                <div
                                    v-for="contact in client.contacts"
                                    :key="contact.id ?? contact.name"
                                    class="text-sm"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ contact.name }}</span>
                                        <span
                                            v-if="contact.is_primary"
                                            class="badge badge-primary badge-xs"
                                        >
                                            {{ t('clients.form.contact.is_primary') }}
                                        </span>
                                    </div>
                                    <p v-if="contact.position" class="text-xs text-base-content/60">
                                        {{ contact.position }}
                                    </p>
                                    <p v-if="contact.email" class="text-xs">
                                        <a :href="`mailto:${contact.email}`" class="link link-hover">
                                            {{ contact.email }}
                                        </a>
                                    </p>
                                    <p v-if="contact.phone" class="text-xs">
                                        <a :href="`tel:${contact.phone}`" class="link link-hover">
                                            {{ contact.phone }}
                                        </a>
                                    </p>
                                </div>
                            </div>
                            <EmptyState
                                v-else
                                :title="t('clients.detail.contact_persons')"
                                :icon="UsersIcon"
                            />
                        </div>
                    </div>

                    <!-- Poznamka -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('clients.detail.note') }}</h2>
                            <p class="whitespace-pre-wrap text-sm mt-2">
                                {{ client.note ?? t('clients.detail.no_note') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit drawer -->
            <ClientFormDrawer
                v-if="ui.editDrawerOpen"
                mode="edit"
                :client="client"
                @close="ui.editDrawerOpen = false"
                @saved="onDrawerSaved"
            />

            <!-- Delete confirm modal -->
            <dialog class="modal" :open="ui.deleteConfirmOpen">
                <div class="modal-box">
                    <h3 class="font-bold text-lg">{{ t('clients.delete') }}</h3>
                    <p class="py-4">
                        {{ t('clients.delete_confirm').replace('{name}', client.name) }}
                    </p>
                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost" @click="ui.deleteConfirmOpen = false">
                            {{ t('clients.form.cancel') }}
                        </button>
                        <button type="button" class="btn btn-error" @click="confirmDelete">
                            {{ t('clients.delete') }}
                        </button>
                    </div>
                </div>
                <div class="modal-backdrop" @click="ui.deleteConfirmOpen = false" />
            </dialog>
        </div>
    </AppLayout>
</template>
