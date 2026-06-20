<script setup lang="ts">
    import { computed } from 'vue';
    import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = defineProps<{
        search: string;
        status: App.Enums.ContractStatusEnum | undefined;
        category: App.Enums.ContractCategoryEnum | undefined;
        termType: App.Enums.ContractTermTypeEnum | undefined;
        statusOptions: SelectOption[];
        categoryOptions: SelectOption[];
        termTypeOptions: SelectOption[];
    }>();

    const emit = defineEmits<{
        'update:search': [string];
        'update:status': [App.Enums.ContractStatusEnum | undefined];
        'update:category': [App.Enums.ContractCategoryEnum | undefined];
        'update:termType': [App.Enums.ContractTermTypeEnum | undefined];
    }>();

    const { t } = useTranslate();

    const statusValue = computed<string>({
        get: () => props.status ?? '',
        set: (val: string | number) => {
            const str = String(val);
            emit('update:status', str ? (str as App.Enums.ContractStatusEnum) : undefined);
        },
    });

    const categoryValue = computed<string>({
        get: () => props.category ?? '',
        set: (val: string | number) => {
            const str = String(val);
            emit('update:category', str ? (str as App.Enums.ContractCategoryEnum) : undefined);
        },
    });

    const termTypeValue = computed<string>({
        get: () => props.termType ?? '',
        set: (val: string | number) => {
            const str = String(val);
            emit('update:termType', str ? (str as App.Enums.ContractTermTypeEnum) : undefined);
        },
    });

    const allStatusOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('contracts.filter.all_statuses') },
        ...props.statusOptions,
    ]);

    const allCategoryOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('contracts.filter.all_categories') },
        ...props.categoryOptions,
    ]);

    const allTermTypeOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('contracts.filter.all_term_types') },
        ...props.termTypeOptions,
    ]);
</script>

<template>
    <div class="flex flex-wrap gap-3 mb-4 items-end">
        <label class="input flex items-center gap-2 flex-1 min-w-48">
            <MagnifyingGlassIcon class="w-4 h-4 opacity-60" />
            <input
                type="text"
                :value="search"
                :placeholder="t('contracts.search_placeholder')"
                class="grow"
                @input="emit('update:search', ($event.target as HTMLInputElement).value)"
            />
        </label>

        <div class="flex-1 min-w-44">
            <SelectInput
                v-model="statusValue"
                :options="allStatusOptions"
                :label="t('contracts.col.status')"
            />
        </div>

        <div class="flex-1 min-w-44">
            <SelectInput
                v-model="categoryValue"
                :options="allCategoryOptions"
                :label="t('contracts.col.category')"
            />
        </div>

        <div class="flex-1 min-w-44">
            <SelectInput
                v-model="termTypeValue"
                :options="allTermTypeOptions"
                :label="t('contracts.col.term_type')"
            />
        </div>
    </div>
</template>
