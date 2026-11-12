<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
    <div class="card bg-base-100">
        <div class="card-body">
            <h2 class="card-title">{{ t('details') }}</h2>

            <div class="flex flex-wrap gap-2">
                <ObjectTypeBadge :type="object.type" />
                <ObjectStatusBadge :is-active="object.is_active" />
            </div>

            <dl class="mt-2 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('client') }}</dt>
                    <dd class="text-right">
                        <Link v-if="object.client_name" :href="`/clients/${object.client_id}`" class="link link-hover">
                            {{ object.client_name }}
                        </Link>
                        <span v-else>{{ t('empty_dash') }}</span>
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('object_area_sqm') }}</dt>
                    <dd>{{ object.area_sqm !== null ? `${object.area_sqm} m²` : t('empty_dash') }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('object_floor') }}</dt>
                    <dd>{{ object.floor !== null ? object.floor : t('empty_dash') }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('address') }}</dt>
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

            <p class="mt-2 text-xs text-muted">
                {{ t('object_created_at', { date: formatDate(object.created_at) }) }}
            </p>
        </div>
    </div>
</template>
