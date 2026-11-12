<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{
    billing: App.Data.Objects.ObjectBillingData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body">
            <h2 class="card-title">{{ t('object_billing') }}</h2>

            <p class="text-xs text-muted">{{ t('object_billing_card_hint') }}</p>

            <p v-if="billing.name" class="font-medium">{{ billing.name }}</p>

            <dl class="space-y-2 text-sm">
                <div v-if="billing.ico" class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('client_ico') }}</dt>
                    <dd>{{ billing.ico }}</dd>
                </div>

                <div v-if="billing.dic" class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('client_dic') }}</dt>
                    <dd>{{ billing.dic }}</dd>
                </div>

                <div v-if="billing.vat_number" class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('client_vat_number') }}</dt>
                    <dd>{{ billing.vat_number }}</dd>
                </div>

                <div v-if="billing.peppol_participant_id" class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('peppol_participant_id') }}</dt>
                    <dd>{{ billing.peppol_participant_id }}</dd>
                </div>

                <div v-if="billing.email" class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('object_billing_email') }}</dt>
                    <dd>{{ billing.email }}</dd>
                </div>

                <div v-if="billing.street || billing.city || billing.postal_code" class="flex justify-between gap-4">
                    <dt class="text-muted">{{ t('address') }}</dt>
                    <dd class="text-right">
                        <address class="not-italic">
                            <template v-if="billing.street">{{ billing.street }}<br /></template>
                            <template v-if="billing.postal_code || billing.city">
                                {{ billing.postal_code }} {{ billing.city }}
                            </template>
                        </address>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</template>
