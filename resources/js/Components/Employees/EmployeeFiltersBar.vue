<script setup lang="ts">
    import { computed } from 'vue';
    import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

    const props = defineProps<{
        search: string;
        role: string | undefined;
        isActive: boolean | undefined;
        roleOptions: SelectOption[];
    }>();

    const emit = defineEmits<{
        (e: 'update:search', v: string): void;
        (e: 'update:role', v: string | undefined): void;
        (e: 'update:isActive', v: boolean | undefined): void;
    }>();

    const { t } = useTranslate();

    const statusOptions = computed<SelectOption[]>(() => [
        { value: '1', label: t('employees.status.active') },
        { value: '0', label: t('employees.status.inactive') },
    ]);

    function onStatusChange(v: string | number | undefined): void {
        if (v === undefined || v === '') {
            emit('update:isActive', undefined);
        } else {
            emit('update:isActive', v === '1' || v === 1);
        }
    }

    const activeStatusValue = computed<string | undefined>(() => {
        if (props.isActive === undefined) return undefined;
        return props.isActive ? '1' : '0';
    });
</script>

<template>
    <div class="flex flex-col md:flex-row gap-3 mb-4">
        <label class="input flex items-center gap-2 flex-1">
            <MagnifyingGlassIcon class="w-4 h-4 opacity-60" />
            <input
                type="text"
                :value="search"
                :placeholder="t('employees.search_placeholder')"
                class="grow"
                @input="emit('update:search', ($event.target as HTMLInputElement).value)"
            />
        </label>

        <select
            class="select select-bordered"
            :value="role ?? ''"
            @change="emit('update:role', ($event.target as HTMLSelectElement).value || undefined)"
        >
            <option value="">{{ t('employees.filter.all_roles') }}</option>
            <option v-for="opt in roleOptions" :key="String(opt.value)" :value="String(opt.value)">
                {{ opt.label }}
            </option>
        </select>

        <select
            class="select select-bordered"
            :value="activeStatusValue ?? ''"
            @change="onStatusChange(($event.target as HTMLSelectElement).value || undefined)"
        >
            <option value="">{{ t('employees.filter.all_statuses') }}</option>
            <option v-for="opt in statusOptions" :key="String(opt.value)" :value="String(opt.value)">
                {{ opt.label }}
            </option>
        </select>
    </div>
</template>
