<script setup lang="ts">
    import { computed } from 'vue';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { useQuoteFilters } from '@/Composables/useQuoteFilters';

    interface FilterInit {
        search?: string | null;
        status?: App.Enums.QuoteStatusEnum | null;
        client_id?: string | null;
        kind?: App.Enums.QuoteKindEnum | null;
        valid_from?: string | null;
        valid_to?: string | null;
    }

    const props = defineProps<{
        filters: FilterInit;
        statusOptions: SelectOption[];
        clients: App.Data.Clients.ClientOptionData[];
    }>();

    const { t } = useTranslate();
    const { state: filterState } = useQuoteFilters(props.filters);

    const clientOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('quotes.filter.all_clients') },
        ...props.clients.map((c) => ({ value: c.id, label: c.name })),
    ]);

    const clientSelectValue = computed({
        get: () => filterState.client_id ?? '',
        set: (val: string | number) => {
            filterState.client_id = val === '' ? undefined : String(val);
        },
    });

    const kindOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('quotes.filter.all_kinds') },
        { value: 'itemized', label: t('quote_kind.itemized') },
        { value: 'document', label: t('quote_kind.document') },
    ]);

    const kindSelectValue = computed({
        get: () => filterState.kind ?? '',
        set: (val: string | number) => {
            filterState.kind = val === '' ? undefined : (String(val) as App.Enums.QuoteKindEnum);
        },
    });

    function toggleStatus(value: string): void {
        filterState.status = filterState.status === value ? undefined : (value as App.Enums.QuoteStatusEnum);
    }
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <input
            v-model="filterState.search"
            type="search"
            :placeholder="t('quotes.search_placeholder')"
            class="input input-bordered input-sm w-full max-w-xs"
        />

        <div class="flex flex-wrap gap-1">
            <button
                v-for="opt in statusOptions"
                :key="opt.value"
                type="button"
                :aria-pressed="filterState.status === String(opt.value)"
                :class="[
                    'btn btn-xs',
                    filterState.status === String(opt.value) ? 'btn-primary' : 'btn-ghost',
                ]"
                @click="toggleStatus(String(opt.value))"
            >
                {{ opt.label }}
            </button>
        </div>

        <div class="w-44">
            <SelectInput v-model="clientSelectValue" :options="clientOptions" />
        </div>

        <div class="w-44">
            <SelectInput v-model="kindSelectValue" :options="kindOptions" />
        </div>

        <div class="flex items-center gap-1">
            <input
                v-model="filterState.valid_from"
                type="date"
                class="input input-bordered input-sm"
                :aria-label="t('quotes.form.issue_date')"
            />
            <span class="text-base-content/40 text-sm">–</span>
            <input
                v-model="filterState.valid_to"
                type="date"
                class="input input-bordered input-sm"
                :aria-label="t('quotes.form.valid_until')"
            />
        </div>
    </div>
</template>
