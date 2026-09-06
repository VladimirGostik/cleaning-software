<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import { formatDatetime } from '@/utils/date';
import { formatBytes } from '@/utils/bytes';
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/solid';
import type { Breadcrumb } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    media: App.Data.MediaDetailData;
}>();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('media_library'), url: '/media' },
    { label: props.media.file_name },
];

const isImage = computed(() => !!props.media.mime_type?.startsWith('image/'));

const customPropsJson = computed(() => {
    const cp = props.media.custom_properties;
    if (!cp || Object.keys(cp).length === 0) return null;
    return JSON.stringify(cp, null, 2);
});
</script>

<template>
    <AppLayout>
        <Header :title="t('media_detail')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <a href="/media" class="btn btn-ghost btn-sm">{{ t('back') }}</a>
            </template>
        </Header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- 1. Basic info card -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('basic_info') }}</h2>
                    <dl class="divide-y divide-base-200">
                        <div class="py-2 flex justify-between gap-4">
                            <dt class="text-sm text-base-content/60">{{ t('file_name') }}</dt>
                            <dd class="text-sm font-medium break-all text-right">{{ media.file_name }}</dd>
                        </div>
                        <div class="py-2 flex justify-between gap-4">
                            <dt class="text-sm text-base-content/60">{{ t('name') }}</dt>
                            <dd class="text-sm break-all text-right">{{ media.name }}</dd>
                        </div>
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('mime_type') }}</dt>
                            <dd>
                                <span v-if="media.mime_type" class="badge badge-ghost badge-sm">{{
                                    media.mime_type
                                }}</span>
                                <span v-else class="text-base-content/40">—</span>
                            </dd>
                        </div>
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('size') }}</dt>
                            <dd class="text-sm">{{ formatBytes(media.size) }}</dd>
                        </div>
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('collection_name') }}</dt>
                            <dd>
                                <span class="badge badge-outline badge-sm">{{ media.collection_name }}</span>
                            </dd>
                        </div>
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('disk') }}</dt>
                            <dd class="text-sm">{{ media.disk }}</dd>
                        </div>
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('created_at') }}</dt>
                            <dd class="text-sm">{{ formatDatetime(media.created_at) }}</dd>
                        </div>
                        <div v-if="media.uuid" class="py-2 flex justify-between gap-4">
                            <dt class="text-sm text-base-content/60">UUID</dt>
                            <dd class="text-xs font-mono break-all text-right">{{ media.uuid }}</dd>
                        </div>
                    </dl>
                    <div class="card-actions mt-2">
                        <a :href="media.url" target="_blank" rel="noopener" class="btn btn-primary btn-sm gap-1">
                            <ArrowTopRightOnSquareIcon class="size-4" />
                            {{ t('open_file') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- 2. Parent model card -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('parent_model') }}</h2>
                    <dl class="divide-y divide-base-200">
                        <div class="py-2 flex justify-between">
                            <dt class="text-sm text-base-content/60">{{ t('owner') }}</dt>
                            <dd class="text-sm">{{ media.model_type_label }}</dd>
                        </div>
                        <div class="py-2 flex justify-between gap-4">
                            <dt class="text-sm text-base-content/60">{{ t('model_type') }}</dt>
                            <dd class="text-xs font-mono break-all text-right">{{ media.model_type }}</dd>
                        </div>
                        <div class="py-2 flex justify-between gap-4">
                            <dt class="text-sm text-base-content/60">{{ t('model_id') }}</dt>
                            <dd class="text-xs font-mono break-all text-right">{{ media.model_id ?? '—' }}</dd>
                        </div>
                    </dl>
                    <div v-if="media.model_url" class="card-actions mt-2">
                        <a :href="media.model_url" class="btn btn-outline btn-sm gap-1">
                            <ArrowTopRightOnSquareIcon class="size-4" />
                            {{ t('open_owner') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- 3. Preview card (full-width) -->
            <div class="card bg-base-100 shadow-sm lg:col-span-2">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('preview') }}</h2>
                    <div v-if="isImage" class="flex justify-center">
                        <img
                            :src="media.url"
                            :alt="media.name"
                            class="max-h-96 rounded-box border border-base-300 object-contain"
                        />
                    </div>
                    <div v-else class="text-sm text-base-content/50 text-center py-8">
                        {{ t('no_preview') }}
                    </div>
                </div>
            </div>

            <!-- 4. Custom properties (only if non-empty) -->
            <div v-if="customPropsJson" class="card bg-base-100 shadow-sm lg:col-span-2">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('custom_properties') }}</h2>
                    <pre class="bg-base-200 rounded-box p-4 text-sm overflow-x-auto">{{ customPropsJson }}</pre>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
