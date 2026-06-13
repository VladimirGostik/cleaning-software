<script setup lang="ts">
    import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';

    defineProps<{
        search: string;
        type: App.Enums.ObjectTypeEnum | undefined;
        client_id: string | undefined;
        is_active: boolean | undefined;
        types: Array<{ value: string; label: string }>;
        clients: Array<{ id: string; name: string }>;
    }>();

    const emit = defineEmits<{
        (e: 'update:search', v: string): void;
        (e: 'update:type', v: App.Enums.ObjectTypeEnum | undefined): void;
        (e: 'update:client_id', v: string | undefined): void;
        (e: 'update:is_active', v: boolean | undefined): void;
    }>();

    const { t } = useTranslate();
</script>

<template>
    <div class="flex flex-col md:flex-row gap-3 mb-4 flex-wrap">
        <label class="input flex items-center gap-2 flex-1 min-w-48">
            <MagnifyingGlassIcon class="w-4 h-4 opacity-60" />
            <input
                type="text"
                :value="search"
                :placeholder="t('objects.search_placeholder')"
                class="grow"
                @input="emit('update:search', ($event.target as HTMLInputElement).value)"
            />
        </label>

        <label :aria-label="t('objects.col.type')" class="sr-only">{{ t('objects.col.type') }}</label>
        <select
            :value="type ?? ''"
            class="select select-bordered select-sm"
            :aria-label="t('objects.col.type')"
            @change="
                emit(
                    'update:type',
                    ($event.target as HTMLSelectElement).value
                        ? (($event.target as HTMLSelectElement).value as App.Enums.ObjectTypeEnum)
                        : undefined,
                )
            "
        >
            <option value="">{{ t('objects.filter.all_types') }}</option>
            <option v-for="opt in types" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>

        <select
            :value="client_id ?? ''"
            class="select select-bordered select-sm"
            :aria-label="t('objects.col.client')"
            @change="emit('update:client_id', ($event.target as HTMLSelectElement).value || undefined)"
        >
            <option value="">{{ t('objects.filter.all_clients') }}</option>
            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>

        <select
            :value="is_active === undefined ? '' : String(is_active)"
            class="select select-bordered select-sm"
            :aria-label="t('objects.col.active')"
            @change="
                emit(
                    'update:is_active',
                    ($event.target as HTMLSelectElement).value === ''
                        ? undefined
                        : ($event.target as HTMLSelectElement).value === 'true',
                )
            "
        >
            <option value="">{{ t('objects.filter.all_states') }}</option>
            <option value="true">{{ t('objects.filter.active') }}</option>
            <option value="false">{{ t('objects.filter.inactive') }}</option>
        </select>
    </div>
</template>
