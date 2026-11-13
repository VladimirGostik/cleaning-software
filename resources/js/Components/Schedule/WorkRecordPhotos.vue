<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{
    photos: App.Data.Schedule.JobWorkRecordPhotoData[];
}>();

const { t } = useI18n();
</script>

<template>
    <ul v-if="photos.length > 0" class="flex flex-wrap gap-2">
        <li v-for="photo in photos" :key="photo.id">
            <a
                :href="photo.url"
                target="_blank"
                rel="noopener"
                class="block size-14 overflow-hidden rounded-tile bg-base-200"
                :aria-label="t('work_record_photo_open', { name: photo.name })"
            >
                <img :src="photo.url" alt="" loading="lazy" class="size-full object-cover" />
            </a>
        </li>
    </ul>
</template>
