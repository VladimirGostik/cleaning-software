<script setup lang="ts">
import { useI18n } from 'vue-i18n';

import ObjectTypeBadge from './ObjectTypeBadge.vue';
import ObjectStatusBadge from './ObjectStatusBadge.vue';
import { formatDate } from '@/utils/date';

defineProps<{
    object: App.Data.Objects.ObjectDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-base">{{ t('details') }}</h2>

            <div class="flex flex-wrap gap-2">
                <ObjectTypeBadge :type="object.type" />
                <ObjectStatusBadge :is-active="object.is_active" />
            </div>

            <dl class="mt-2 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('client') }}</dt>
                    <dd class="text-right">
                        <a v-if="object.client_name" :href="`/clients/${object.client_id}`" class="link link-hover">
                            {{ object.client_name }}
                        </a>
                        <span v-else>{{ t('empty_dash') }}</span>
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('object_area_sqm') }}</dt>
                    <dd>{{ object.area_sqm !== null ? `${object.area_sqm} m²` : t('empty_dash') }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('object_floor') }}</dt>
                    <dd>{{ object.floor !== null ? object.floor : t('empty_dash') }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('address') }}</dt>
                    <dd class="text-right">
                        <address class="not-italic">
                            <template v-if="object.street">{{ object.street }}<br /></template>
                            <template v-if="object.postal_code || object.city">
                                {{ object.postal_code }} {{ object.city }}<br />
                            </template>
                            {{ object.country }}
                        </address>
                    </dd>
                </div>
            </dl>

            <p class="mt-2 text-xs text-base-content/50">
                {{ t('object_created_at', { date: formatDate(object.created_at) }) }}
            </p>
        </div>
    </div>
</template>
