<script setup lang="ts">
    import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

    defineProps<{
        search: string;
        status: App.Enums.JobStatusEnum | undefined;
        type: App.Enums.JobTypeEnum | undefined;
        dateFrom: string | undefined;
        dateTo: string | undefined;
        statusOptions: SelectOption[];
        typeOptions: SelectOption[];
    }>();

    const emit = defineEmits<{
        (e: 'update:search', v: string): void;
        (e: 'update:status', v: App.Enums.JobStatusEnum | undefined): void;
        (e: 'update:type', v: App.Enums.JobTypeEnum | undefined): void;
        (e: 'update:dateFrom', v: string | undefined): void;
        (e: 'update:dateTo', v: string | undefined): void;
    }>();

    const { t } = useTranslate();
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <label class="input flex items-center gap-2 flex-1 min-w-[200px]">
            <MagnifyingGlassIcon class="w-4 h-4 opacity-60" />
            <input
                type="text"
                :value="search"
                :placeholder="t('schedule.search_placeholder')"
                class="grow"
                @input="emit('update:search', ($event.target as HTMLInputElement).value)"
            />
        </label>

        <select
            class="select select-bordered"
            :value="status ?? ''"
            @change="
                emit(
                    'update:status',
                    (($event.target as HTMLSelectElement).value || undefined) as
                        | App.Enums.JobStatusEnum
                        | undefined,
                )
            "
        >
            <option value="">{{ t('schedule.filter.all_statuses') }}</option>
            <option v-for="opt in statusOptions" :key="String(opt.value)" :value="String(opt.value)">
                {{ opt.label }}
            </option>
        </select>

        <select
            class="select select-bordered"
            :value="type ?? ''"
            @change="
                emit(
                    'update:type',
                    (($event.target as HTMLSelectElement).value || undefined) as
                        | App.Enums.JobTypeEnum
                        | undefined,
                )
            "
        >
            <option value="">{{ t('schedule.filter.all_types') }}</option>
            <option v-for="opt in typeOptions" :key="String(opt.value)" :value="String(opt.value)">
                {{ opt.label }}
            </option>
        </select>

        <input
            type="date"
            class="input input-bordered input-sm"
            :value="dateFrom ?? ''"
            :aria-label="t('schedule.col.date')"
            @change="
                emit(
                    'update:dateFrom',
                    ($event.target as HTMLInputElement).value || undefined,
                )
            "
        />

        <span class="text-base-content/40 text-sm">–</span>

        <input
            type="date"
            class="input input-bordered input-sm"
            :value="dateTo ?? ''"
            :aria-label="t('schedule.col.date')"
            @change="
                emit(
                    'update:dateTo',
                    ($event.target as HTMLInputElement).value || undefined,
                )
            "
        />
    </div>
</template>
