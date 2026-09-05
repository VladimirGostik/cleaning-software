<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import { formatDatetime } from '@/utils/date';
import type { Breadcrumb } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    activity: App.Data.ActivityLogDetailData;
}>();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('audit_logs'), url: '/audit-logs' },
    { label: props.activity.description },
];

function formatJson(data: Record<string, unknown> | null): string {
    if (!data) return '';
    return JSON.stringify(data, null, 2);
}
</script>

<template>
    <AppLayout>
        <Header
            :title="t('audit_log_detail')"
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <a href="/audit-logs" class="btn btn-ghost btn-sm">
                    {{ t('back') }}
                </a>
            </template>
        </Header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">
                        {{ t('basic_info') }}
                    </h2>
                    <dl class="divide-y divide-base-200">
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('description') }}</dt>
                            <dd class="text-sm font-medium">{{ activity.description }}</dd>
                        </div>
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('event') }}</dt>
                            <dd>
                                <span v-if="activity.event" class="badge badge-ghost badge-sm">
                                    {{ activity.event }}
                                </span>
                                <span v-else>-</span>
                            </dd>
                        </div>
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('subject_type') }}</dt>
                            <dd class="text-sm">{{ activity.subject_type ?? '-' }}</dd>
                        </div>
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('subject_id') }}</dt>
                            <dd class="text-sm">{{ activity.subject_id ?? '-' }}</dd>
                        </div>
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('created_at') }}</dt>
                            <dd class="text-sm">{{ formatDatetime(activity.created_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('causer') }}</h2>
                    <dl class="divide-y divide-base-200">
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('name') }}</dt>
                            <dd class="text-sm font-medium">{{ activity.causer_name ?? '-' }}</dd>
                        </div>
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('email') }}</dt>
                            <dd class="text-sm">{{ activity.causer_email ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div
                v-if="activity.attribute_changes"
                class="card bg-base-100 shadow-sm lg:col-span-2"
            >
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('attribute_changes') }}</h2>
                    <pre class="bg-base-200 rounded-box p-4 text-sm overflow-x-auto">{{ formatJson(activity.attribute_changes) }}</pre>
                </div>
            </div>

            <div
                v-if="activity.properties"
                class="card bg-base-100 shadow-sm lg:col-span-2"
            >
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('properties') }}</h2>
                    <pre class="bg-base-200 rounded-box p-4 text-sm overflow-x-auto">{{ formatJson(activity.properties) }}</pre>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
