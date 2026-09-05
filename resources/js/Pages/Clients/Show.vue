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
        PlusIcon,
    } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import Can from '@/Components/Can.vue';
    import ConfirmDialog from '@/Components/ConfirmDialog.vue';
    import ClientTypeBadge from '@/Components/Clients/ClientTypeBadge.vue';
    import ClientFormDrawer from '@/Components/Clients/ClientFormDrawer.vue';
    import ObjectTypeBadge from '@/Components/Objects/ObjectTypeBadge.vue';
    import ObjectFormDrawer from '@/Components/Objects/ObjectFormDrawer.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useLocalizedDate } from '@/Composables/useLocalizedDate';

    interface Props {
        client: App.Data.Clients.ClientDetailData;
        objects: App.Data.Objects.ObjectListItemData[];
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const { formatDate } = useLocalizedDate();
    const flash = computed(() => pageProps.flash);

    const primaryContact = computed(() => props.client.contacts?.find((c) => c.is_primary) ?? null);

    const ui = reactive({
        editDrawerOpen: false,
        deleteConfirmOpen: false,
        objectDrawerOpen: false,
    });

    const subtitle = computed(() =>
        t('clients.detail.customer_since').replace('{date}', formatDate(props.client.created_at)),
    );

    function onDrawerSaved() {
        ui.editDrawerOpen = false;
        router.reload({ only: ['client'] });
    }

    function onObjectDrawerSaved() {
        ui.objectDrawerOpen = false;
        router.reload({ only: ['objects'] });
    }

    function confirmDelete() {
        router.delete(`/clients/${props.client.id}`, {
            onSuccess: () => router.visit('/clients'),
        });
    }
</script>

<template>
    <div class="page-container">
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
                <Can permission="edit clients">
                    <button type="button" class="btn btn-primary" @click="ui.editDrawerOpen = true">
                        <PencilSquareIcon class="w-4 h-4" />
                        {{ t('clients.edit') }}
                    </button>
                </Can>

                <button type="button" class="btn btn-ghost" disabled>
                    <DocumentTextIcon class="w-4 h-4" />
                    {{ t('clients.detail.create_quote') }}
                </button>

                <Can permission="create objects">
                    <button type="button" class="btn btn-ghost" @click="ui.objectDrawerOpen = true">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('clients.detail.add_object') }}
                    </button>
                    <template #fallback>
                        <button type="button" class="btn btn-ghost" disabled>
                            <BuildingOffice2Icon class="w-4 h-4" />
                            {{ t('clients.detail.add_object') }}
                        </button>
                    </template>
                </Can>

                <Can permission="delete clients">
                    <div class="dropdown dropdown-end">
                        <div tabindex="0" role="button" class="btn btn-ghost btn-square">
                            <EllipsisVerticalIcon class="w-5 h-5" />
                        </div>
                        <ul
                            tabindex="0"
                            class="dropdown-content menu bg-base-100 rounded-box z-10 w-40 p-2 shadow"
                        >
                            <li>
                                <button type="button" class="text-error" @click="ui.deleteConfirmOpen = true">
                                    <TrashIcon class="w-4 h-4" />
                                    {{ t('clients.delete') }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </Can>
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
                        <div v-if="objects.length" class="overflow-x-auto">
                            <table class="table table-zebra w-full">
                                <thead>
                                    <tr>
                                        <th>{{ t('objects.col.name') }}</th>
                                        <th>{{ t('objects.col.type') }}</th>
                                        <th>{{ t('objects.col.area') }}</th>
                                        <th>{{ t('objects.col.active') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="obj in objects" :key="obj.id" class="hover">
                                        <td>
                                            <Link
                                                :href="`/objects/${obj.id}`"
                                                class="font-medium link link-hover"
                                            >
                                                {{ obj.name }}
                                            </Link>
                                            <p v-if="obj.city" class="text-xs opacity-60">{{ obj.city }}</p>
                                        </td>
                                        <td>
                                            <ObjectTypeBadge :type="obj.type" />
                                        </td>
                                        <td>{{ obj.area_sqm ?? t('common.empty_dash') }}</td>
                                        <td>
                                            <span v-if="obj.is_active" class="badge badge-success badge-sm">
                                                {{ t('objects.active') }}
                                            </span>
                                            <span v-else class="badge badge-ghost badge-sm">
                                                {{ t('objects.inactive') }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <EmptyState
                            v-else
                            :title="t('clients.detail.no_objects')"
                            :icon="BuildingOffice2Icon"
                        />
                    </div>
                </div>

                <!-- Zmluvy -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title">{{ t('clients.detail.contracts') }}</h2>
                        <EmptyState :title="t('clients.detail.no_contracts')" :icon="DocumentDuplicateIcon" />
                    </div>
                </div>

                <!-- Faktury -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title">{{ t('clients.detail.invoices') }}</h2>
                        <EmptyState :title="t('clients.detail.no_invoices')" :icon="ReceiptPercentIcon" />
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
                            <div v-if="primaryContact?.email" class="flex items-center gap-2">
                                <EnvelopeIcon class="w-4 h-4 text-base-content/50 shrink-0" />
                                <a :href="`mailto:${primaryContact.email}`" class="link link-hover">
                                    {{ primaryContact.email }}
                                </a>
                            </div>
                            <div v-if="primaryContact?.phone" class="flex items-center gap-2">
                                <PhoneIcon class="w-4 h-4 text-base-content/50 shrink-0" />
                                <a :href="`tel:${primaryContact.phone}`" class="link link-hover">
                                    {{ primaryContact.phone }}
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
                                <span class="text-base-content/60"
                                    >{{ t('clients.form.is_vat_payer') }}:</span
                                >
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
                                    <span v-if="contact.is_primary" class="badge badge-primary badge-xs">
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
                        <EmptyState v-else :title="t('clients.detail.contact_persons')" :icon="UsersIcon" />
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

        <!-- Object create drawer -->
        <ObjectFormDrawer
            v-if="ui.objectDrawerOpen"
            mode="create"
            :clients="[{ id: client.id, name: client.name }]"
            @close="ui.objectDrawerOpen = false"
            @saved="onObjectDrawerSaved"
        />

        <!-- Delete confirm modal -->
        <ConfirmDialog
            :open="ui.deleteConfirmOpen"
            :title="t('clients.delete')"
            :body="t('clients.delete_confirm').replace('{name}', client.name)"
            :confirm-label="t('clients.delete')"
            :cancel-label="t('clients.form.cancel')"
            confirm-variant="error"
            @confirm="confirmDelete"
            @cancel="ui.deleteConfirmOpen = false"
        />
    </div>
</template>
