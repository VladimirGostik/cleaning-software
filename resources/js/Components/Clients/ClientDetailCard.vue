<script setup lang="ts">
import { useI18n } from 'vue-i18n';

import ClientTypeBadge from './ClientTypeBadge.vue';
import { formatDate } from '@/utils/date';

defineProps<{
    client: App.Data.Clients.ClientDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-base">{{ t('basic_info') }}</h2>

            <div class="flex flex-wrap gap-2">
                <ClientTypeBadge :type="client.type" />
                <span v-if="client.is_vat_payer" class="badge badge-outline">
                    {{ t('client_is_vat_payer') }}
                </span>
            </div>

            <dl class="mt-2 space-y-2 text-sm">
                <div v-if="client.ico" class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('client_ico') }}</dt>
                    <dd>{{ client.ico }}</dd>
                </div>

                <div v-if="client.dic" class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('client_dic') }}</dt>
                    <dd>{{ client.dic }}</dd>
                </div>

                <div v-if="client.vat_number" class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('client_vat_number') }}</dt>
                    <dd>{{ client.vat_number }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('address') }}</dt>
                    <dd class="text-right">
                        <address class="not-italic">
                            <template v-if="client.street">{{ client.street }}<br /></template>
                            <template v-if="client.postal_code || client.city">
                                {{ client.postal_code }} {{ client.city }}<br />
                            </template>
                            {{ client.country }}
                        </address>
                    </dd>
                </div>
            </dl>

            <p class="mt-2 text-xs text-base-content/50">
                {{ t('client_customer_since', { date: formatDate(client.created_at) }) }}
            </p>
        </div>
    </div>
</template>
