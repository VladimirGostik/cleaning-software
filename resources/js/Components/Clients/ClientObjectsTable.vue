<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useRowLink } from '@/Composables/useRowLink';
import BuildingOffice2Icon from '~icons/material-symbols/apartment-outline-rounded';

import EmptyState from '@/Components/EmptyState.vue';
import ObjectTypeBadge from '@/Components/Objects/ObjectTypeBadge.vue';
import ObjectStatusBadge from '@/Components/Objects/ObjectStatusBadge.vue';

defineProps<{
    objects: App.Data.Objects.ObjectListItemData[];
}>();

const { t } = useI18n();
const rowLink = useRowLink();

const objectHref = (object: App.Data.Objects.ObjectListItemData) => `/objects/${object.id}`;
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body">
            <h2 class="card-title">{{ t('client_objects') }}</h2>

            <EmptyState v-if="objects.length === 0" :title="t('client_no_objects')" :icon="BuildingOffice2Icon" />

            <div v-else class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t('name') }}</th>
                            <th>{{ t('type') }}</th>
                            <th>{{ t('object_area_sqm') }}</th>
                            <th>{{ t('status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="object in objects"
                            :key="object.id"
                            :class="rowLink.rowClass(objectHref(object))"
                            @click="rowLink.onClick(objectHref(object), $event)"
                            @auxclick="rowLink.onAuxClick(objectHref(object), $event)"
                        >
                            <td>
                                <Link :href="objectHref(object)" class="link link-hover font-medium">
                                    {{ object.name }}
                                </Link>
                                <div v-if="object.city" class="text-xs text-muted">{{ object.city }}</div>
                            </td>
                            <td><ObjectTypeBadge :type="object.type" /></td>
                            <td>{{ object.area_sqm !== null ? `${object.area_sqm} m²` : t('empty_dash') }}</td>
                            <td><ObjectStatusBadge :is-active="object.is_active" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
