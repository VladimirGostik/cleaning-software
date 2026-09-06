<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { BuildingOffice2Icon } from '@heroicons/vue/24/outline';

import EmptyState from '@/Components/EmptyState.vue';
import ObjectTypeBadge from '@/Components/Objects/ObjectTypeBadge.vue';
import ObjectStatusBadge from '@/Components/Objects/ObjectStatusBadge.vue';

defineProps<{
    objects: App.Data.Objects.ObjectListItemData[];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-base">{{ t('client_objects') }}</h2>

            <EmptyState v-if="objects.length === 0" :title="t('client_no_objects')" :icon="BuildingOffice2Icon" />

            <div v-else class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ t('name') }}</th>
                            <th>{{ t('type') }}</th>
                            <th>{{ t('object_area_sqm') }}</th>
                            <th>{{ t('status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="object in objects" :key="object.id">
                            <td>
                                <a :href="`/objects/${object.id}`" class="link link-hover font-medium">
                                    {{ object.name }}
                                </a>
                                <div v-if="object.city" class="text-xs text-base-content/60">{{ object.city }}</div>
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
