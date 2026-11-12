<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import { formatDatetime } from '@/utils/date';
import { formatBytes } from '@/utils/bytes';
import ArrowTopRightOnSquareIcon from '~icons/material-symbols/open-in-new-outline-rounded';
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
    <Header :title="t('media_detail')" :breadcrumbs="breadcrumbs">
        <template #actions>
            <Link href="/media" class="btn btn-ghost">{{ t('back') }}</Link>
        </template>
    </Header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="card bg-base-100">
            <div class="card-body">
                <h2 class="card-title">{{ t('basic_info') }}</h2>
                <dl class="kg-list">
                    <div class="kg-list-row justify-between gap-4">
                        <dt class="text-sm text-muted">{{ t('file_name') }}</dt>
                        <dd class="text-sm font-medium break-all text-right">{{ media.file_name }}</dd>
                    </div>
                    <div class="kg-list-row justify-between gap-4">
                        <dt class="text-sm text-muted">{{ t('name') }}</dt>
                        <dd class="text-sm break-all text-right">{{ media.name }}</dd>
                    </div>
                    <div class="kg-list-row justify-between gap-4">
                        <dt class="text-sm text-muted">{{ t('mime_type') }}</dt>
                        <dd>
                            <span v-if="media.mime_type" class="kg-chip kg-chip--neutral kg-chip--sm">{{
                                media.mime_type
                            }}</span>
                            <span v-else class="text-muted">—</span>
                        </dd>
                    </div>
                    <div class="kg-list-row justify-between gap-4">
                        <dt class="text-sm text-muted">{{ t('size') }}</dt>
                        <dd class="text-sm">{{ formatBytes(media.size) }}</dd>
                    </div>
                    <div class="kg-list-row justify-between gap-4">
                        <dt class="text-sm text-muted">{{ t('collection_name') }}</dt>
                        <dd>
                            <span class="kg-chip kg-chip--neutral kg-chip--sm">{{ media.collection_name }}</span>
                        </dd>
                    </div>
                    <div class="kg-list-row justify-between gap-4">
                        <dt class="text-sm text-muted">{{ t('disk') }}</dt>
                        <dd class="text-sm">{{ media.disk }}</dd>
                    </div>
                    <div class="kg-list-row justify-between gap-4">
                        <dt class="text-sm text-muted">{{ t('created_at') }}</dt>
                        <dd class="text-sm">{{ formatDatetime(media.created_at) }}</dd>
                    </div>
                    <div v-if="media.uuid" class="kg-list-row justify-between gap-4">
                        <dt class="text-sm text-muted">UUID</dt>
                        <dd class="text-xs font-mono break-all text-right">{{ media.uuid }}</dd>
                    </div>
                </dl>
                <div class="card-actions mt-2">
                    <a :href="media.url" target="_blank" rel="noopener" class="btn btn-primary btn-sm gap-1">
                        <ArrowTopRightOnSquareIcon />
                        {{ t('open_file') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="card bg-base-100">
            <div class="card-body">
                <h2 class="card-title">{{ t('parent_model') }}</h2>
                <dl class="kg-list">
                    <div class="kg-list-row justify-between gap-4">
                        <dt class="text-sm text-muted">{{ t('owner') }}</dt>
                        <dd class="text-sm">{{ media.model_type_label }}</dd>
                    </div>
                    <div class="kg-list-row justify-between gap-4">
                        <dt class="text-sm text-muted">{{ t('model_type') }}</dt>
                        <dd class="text-xs font-mono break-all text-right">{{ media.model_type }}</dd>
                    </div>
                    <div class="kg-list-row justify-between gap-4">
                        <dt class="text-sm text-muted">{{ t('model_id') }}</dt>
                        <dd class="text-xs font-mono break-all text-right">{{ media.model_id ?? '—' }}</dd>
                    </div>
                </dl>
                <div v-if="media.model_url" class="card-actions mt-2">
                    <Link :href="media.model_url" class="btn btn-neutral btn-sm gap-1">
                        <ArrowTopRightOnSquareIcon />
                        {{ t('open_owner') }}
                    </Link>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 lg:col-span-2">
            <div class="card-body">
                <h2 class="card-title">{{ t('preview') }}</h2>
                <div v-if="isImage" class="flex justify-center">
                    <img :src="media.url" :alt="media.name" class="max-h-96 rounded-card-nested object-contain" />
                </div>
                <div v-else class="text-sm text-muted text-center py-8">
                    {{ t('no_preview') }}
                </div>
            </div>
        </div>

        <div v-if="customPropsJson" class="card bg-base-100 lg:col-span-2">
            <div class="card-body">
                <h2 class="card-title">{{ t('custom_properties') }}</h2>
                <pre class="rounded-card-nested p-4 text-sm overflow-x-auto">{{ customPropsJson }}</pre>
            </div>
        </div>
    </div>
</template>
