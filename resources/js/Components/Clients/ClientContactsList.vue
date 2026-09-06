<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { UsersIcon } from '@heroicons/vue/24/outline';

import EmptyState from '@/Components/EmptyState.vue';

defineProps<{
    contacts: App.Data.Clients.ClientContactData[];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-base">{{ t('client_contacts') }}</h2>

            <EmptyState v-if="contacts.length === 0" :title="t('client_no_contacts')" :icon="UsersIcon" />

            <ul v-else class="space-y-3">
                <li v-for="contact in contacts" :key="contact.id ?? contact.name" class="text-sm">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ contact.name }}</span>
                        <span v-if="contact.is_primary" class="badge badge-primary badge-xs">
                            {{ t('client_contact_is_primary') }}
                        </span>
                    </div>
                    <p v-if="contact.position" class="text-xs text-base-content/60">{{ contact.position }}</p>
                    <div class="mt-1 flex flex-col gap-0.5 text-base-content/80">
                        <a v-if="contact.email" :href="`mailto:${contact.email}`" class="link link-hover">
                            {{ contact.email }}
                        </a>
                        <a v-if="contact.phone" :href="`tel:${contact.phone}`" class="link link-hover">
                            {{ contact.phone }}
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
