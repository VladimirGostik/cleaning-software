<script setup lang="ts">
    import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';

    defineProps<{
        search: string;
        type: App.Enums.ClientTypeEnum | undefined;
        types: Array<{ value: string; label: string }>;
    }>();

    const emit = defineEmits<{
        (e: 'update:search', v: string): void;
        (e: 'update:type', v: App.Enums.ClientTypeEnum | undefined): void;
    }>();

    const { t } = useTranslate();
</script>

<template>
    <div class="flex flex-col md:flex-row gap-3 mb-4">
        <label class="input flex items-center gap-2 flex-1">
            <MagnifyingGlassIcon class="w-4 h-4 opacity-60" />
            <input
                type="text"
                :value="search"
                :placeholder="t('clients.search_placeholder')"
                class="grow"
                @input="emit('update:search', ($event.target as HTMLInputElement).value)"
            />
        </label>
        <div class="join">
            <button
                type="button"
                :class="['join-item btn btn-sm', !type && 'btn-active']"
                @click="emit('update:type', undefined)"
            >
                {{ t('clients.filter.all') }}
            </button>
            <button
                type="button"
                :class="['join-item btn btn-sm', type === 'corporate' && 'btn-active']"
                @click="emit('update:type', 'corporate')"
            >
                {{ t('clients.filter.corporate') }}
            </button>
            <button
                type="button"
                :class="['join-item btn btn-sm', type === 'private' && 'btn-active']"
                @click="emit('update:type', 'private')"
            >
                {{ t('clients.filter.private') }}
            </button>
        </div>
    </div>
</template>
