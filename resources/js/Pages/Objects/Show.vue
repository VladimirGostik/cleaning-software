<script setup lang="ts">
    import { reactive, computed } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import {
        PencilSquareIcon,
        NoSymbolIcon,
        MapPinIcon,
        EllipsisVerticalIcon,
        KeyIcon,
        BuildingOffice2Icon,
    } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import Can from '@/Components/Can.vue';
    import ConfirmDialog from '@/Components/ConfirmDialog.vue';
    import ObjectTypeBadge from '@/Components/Objects/ObjectTypeBadge.vue';
    import ObjectFormDrawer from '@/Components/Objects/ObjectFormDrawer.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useLocalizedDate } from '@/Composables/useLocalizedDate';
    import WorkBreakdownView from '@/Components/Schedule/WorkBreakdownView.vue';

    interface Props {
        object: App.Data.Objects.ObjectDetailData;
        clients: Array<{ id: string; name: string }>;
        workBreakdowns: App.Data.Schedule.WorkBreakdownDetailData[];
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const { formatDate } = useLocalizedDate();
    const flash = computed(() => pageProps.flash);

    const ui = reactive({ editDrawerOpen: false, deactivateConfirmOpen: false });

    const subtitle = computed(() =>
        t('objects.detail.created').replace('{date}', formatDate(props.object.created_at)),
    );

    function onDrawerSaved() {
        ui.editDrawerOpen = false;
        router.reload({ only: ['object'] });
    }

    function confirmDeactivate(): void {
        router.post(
            `/objects/${props.object.id}/deactivate`,
            {},
            {
                onSuccess: () => {
                    ui.deactivateConfirmOpen = false;
                },
            },
        );
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
                    <Link href="/objects">{{ t('objects.title') }}</Link>
                </li>
                <li>{{ object.name }}</li>
            </ul>
        </div>

        <PageHeader :title="object.name" :subtitle="subtitle">
            <template #badges>
                <ObjectTypeBadge :type="object.type" />
                <span :class="['badge', object.is_active ? 'badge-success' : 'badge-ghost']">
                    {{ object.is_active ? t('objects.active') : t('objects.inactive') }}
                </span>
            </template>
            <template #actions>
                <Can permission="edit objects">
                    <button type="button" class="btn btn-primary" @click="ui.editDrawerOpen = true">
                        <PencilSquareIcon class="w-4 h-4" />
                        {{ t('objects.edit') }}
                    </button>
                </Can>

                <Can permission="delete objects">
                    <div v-if="object.is_active" class="dropdown dropdown-end">
                        <div
                            tabindex="0"
                            role="button"
                            class="btn btn-ghost btn-square"
                            :aria-label="t('objects.col.actions')"
                        >
                            <EllipsisVerticalIcon class="w-5 h-5" />
                        </div>
                        <ul
                            tabindex="0"
                            class="dropdown-content menu bg-base-100 rounded-box z-10 w-40 p-2 shadow"
                        >
                            <li>
                                <button type="button" @click="ui.deactivateConfirmOpen = true">
                                    <NoSymbolIcon class="w-4 h-4" />
                                    {{ t('objects.deactivate') }}
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
                <!-- Adresa -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">
                            <MapPinIcon class="w-5 h-5" />
                            {{ t('objects.detail.address') }}
                        </h2>
                        <address class="not-italic text-sm mt-2 space-y-1">
                            <p v-if="object.street">{{ object.street }}</p>
                            <p v-if="object.city || object.postal_code">
                                {{ object.postal_code }} {{ object.city }}
                            </p>
                            <p v-if="object.country">{{ object.country }}</p>
                        </address>
                        <p
                            v-if="!object.street && !object.city && !object.postal_code"
                            class="text-sm text-base-content/50"
                        >
                            {{ t('common.empty_dash') }}
                        </p>
                    </div>
                </div>

                <!-- Pristup -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">
                            <KeyIcon class="w-5 h-5" />
                            {{ t('objects.detail.access') }}
                        </h2>
                        <dl class="space-y-2 text-sm mt-2">
                            <div v-if="object.access_code" class="flex gap-2">
                                <dt class="text-base-content/60 shrink-0">
                                    {{ t('objects.form.access_code') }}:
                                </dt>
                                <dd>{{ object.access_code }}</dd>
                            </div>
                            <div v-if="object.key_box_code" class="flex gap-2">
                                <dt class="text-base-content/60 shrink-0">
                                    {{ t('objects.form.key_box_code') }}:
                                </dt>
                                <dd>{{ object.key_box_code }}</dd>
                            </div>
                            <div v-if="object.key_count !== null" class="flex gap-2">
                                <dt class="text-base-content/60 shrink-0">
                                    {{ t('objects.form.key_count') }}:
                                </dt>
                                <dd>{{ object.key_count }}</dd>
                            </div>
                            <p
                                v-if="
                                    !object.access_code && !object.key_box_code && object.key_count === null
                                "
                                class="text-base-content/50"
                            >
                                {{ t('common.empty_dash') }}
                            </p>
                        </dl>
                    </div>
                </div>

                <!-- Pokyny -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('objects.detail.instructions') }}</h2>
                        <p class="whitespace-pre-wrap text-sm mt-2">
                            {{ object.special_instructions ?? t('objects.detail.no_instructions') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right: 1/3 -->
            <div class="space-y-6">
                <!-- Detail kartica -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">
                            <BuildingOffice2Icon class="w-5 h-5" />
                            {{ t('objects.detail.details') }}
                        </h2>
                        <dl class="space-y-2 text-sm mt-2">
                            <div class="flex justify-between">
                                <dt class="text-base-content/60">{{ t('objects.detail.client') }}</dt>
                                <dd>
                                    <Link
                                        v-if="object.client_id"
                                        :href="`/clients/${object.client_id}`"
                                        class="link link-hover"
                                    >
                                        {{ object.client_name ?? t('common.empty_dash') }}
                                    </Link>
                                    <span v-else>{{ t('common.empty_dash') }}</span>
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-base-content/60">{{ t('objects.col.type') }}</dt>
                                <dd>
                                    <ObjectTypeBadge :type="object.type" />
                                </dd>
                            </div>
                            <div v-if="object.area_sqm" class="flex justify-between">
                                <dt class="text-base-content/60">{{ t('objects.detail.area') }}</dt>
                                <dd>{{ object.area_sqm }} m²</dd>
                            </div>
                            <div v-if="object.floor !== null" class="flex justify-between">
                                <dt class="text-base-content/60">{{ t('objects.detail.floor') }}</dt>
                                <dd>{{ object.floor }}</dd>
                            </div>
                            <div class="flex justify-between pt-1">
                                <dt class="text-base-content/60">{{ t('objects.col.active') }}</dt>
                                <dd>{{ object.is_active ? t('common.yes') : t('common.no') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Work breakdowns (read-only) -->
        <div v-if="workBreakdowns.length > 0" class="mt-6">
            <h2 class="text-sm font-semibold text-base-content/60 uppercase tracking-wide mb-3">
                {{ t('objects.section.work_breakdowns') }}
            </h2>
            <div class="space-y-4">
                <WorkBreakdownView v-for="wb in workBreakdowns" :key="wb.id" :breakdown="wb" />
            </div>
        </div>

        <!-- Edit drawer -->
        <ObjectFormDrawer
            v-if="ui.editDrawerOpen"
            mode="edit"
            :object="object"
            :clients="clients"
            @close="ui.editDrawerOpen = false"
            @saved="onDrawerSaved"
        />

        <!-- Delete confirm modal -->
        <ConfirmDialog
            :open="ui.deactivateConfirmOpen"
            :title="t('objects.deactivate')"
            :body="t('objects.deactivate_confirm').replace('{name}', object.name)"
            :confirm-label="t('objects.deactivate')"
            :cancel-label="t('objects.form.cancel')"
            confirm-variant="error"
            @confirm="confirmDeactivate"
            @cancel="ui.deactivateConfirmOpen = false"
        />
    </div>
</template>
