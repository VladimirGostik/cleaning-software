<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import UsersIcon from '~icons/material-symbols/manage-accounts-outline-rounded';

import EmptyState from '@/Components/EmptyState.vue';
import type { ContactRow } from './contact';

const props = defineProps<{
    contacts: ContactRow[];
    title: string;
    emptyLabel: string;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body">
            <h2 class="card-title">{{ props.title }}</h2>

            <EmptyState v-if="contacts.length === 0" :title="props.emptyLabel" :icon="UsersIcon" />

            <ul v-else class="space-y-3">
                <li v-for="contact in contacts" :key="contact.id ?? contact.name" class="text-sm">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ contact.name }}</span>
                        <span v-if="contact.is_primary" class="kg-chip kg-chip--planned kg-chip--sm">
                            {{ t('contact_is_primary') }}
                        </span>
                    </div>
                    <p v-if="contact.position" class="text-xs text-muted">{{ contact.position }}</p>
                    <div class="mt-1 flex flex-col gap-0.5 text-muted">
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
