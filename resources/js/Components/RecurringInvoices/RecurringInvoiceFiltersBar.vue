<script setup lang="ts">
    import { computed } from 'vue';
    import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';

    const props = defineProps<{
        search: string;
        status: App.Enums.RecurringInvoiceStatusEnum | undefined;
        frequency: App.Enums.RecurringFrequencyEnum | undefined;
        clientId: string | undefined;
        statuses: Array<{ value: string; label: string }>;
        frequencies: Array<{ value: string; label: string }>;
        clients: Array<{ id: string; name: string }>;
    }>();

    const emit = defineEmits<{
        (e: 'update:search', v: string): void;
        (e: 'update:status', v: App.Enums.RecurringInvoiceStatusEnum | undefined): void;
        (e: 'update:frequency', v: App.Enums.RecurringFrequencyEnum | undefined): void;
        (e: 'update:clientId', v: string | undefined): void;
    }>();

    const { t } = useTranslate();

    const statusValue = computed<string>({
        get: () => props.status ?? '',
        set: (val: string | number) => {
            const str = String(val);
            emit('update:status', str ? (str as App.Enums.RecurringInvoiceStatusEnum) : undefined);
        },
    });

    const frequencyValue = computed<string>({
        get: () => props.frequency ?? '',
        set: (val: string | number) => {
            const str = String(val);
            emit('update:frequency', str ? (str as App.Enums.RecurringFrequencyEnum) : undefined);
        },
    });

    const clientValue = computed<string>({
        get: () => props.clientId ?? '',
        set: (val: string | number) => {
            const str = String(val);
            emit('update:clientId', str || undefined);
        },
    });

    const statusOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('recurring_invoices.filter.all_statuses') },
        ...props.statuses.map((s) => ({ value: s.value, label: s.label })),
    ]);

    const frequencyOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('recurring_invoices.filter.all_frequencies') },
        ...props.frequencies.map((f) => ({ value: f.value, label: f.label })),
    ]);

    const clientOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('recurring_invoices.filter.all_clients') },
        ...props.clients.map((c) => ({ value: c.id, label: c.name })),
    ]);
</script>

<template>
    <div class="flex flex-wrap gap-3 mb-4 items-end">
        <label class="input flex items-center gap-2 flex-1 min-w-48">
            <MagnifyingGlassIcon class="w-4 h-4 opacity-60" />
            <input
                type="text"
                :value="search"
                :placeholder="t('recurring_invoices.search_placeholder')"
                class="grow"
                @input="emit('update:search', ($event.target as HTMLInputElement).value)"
            />
        </label>

        <div class="flex-1 min-w-44">
            <SelectInput
                v-model="statusValue"
                :options="statusOptions"
                :label="t('recurring_invoices.col.status')"
            />
        </div>

        <div class="flex-1 min-w-44">
            <SelectInput
                v-model="frequencyValue"
                :options="frequencyOptions"
                :label="t('recurring_invoices.col.frequency')"
            />
        </div>

        <div class="flex-1 min-w-44">
            <SelectInput
                v-model="clientValue"
                :options="clientOptions"
                :label="t('recurring_invoices.col.client')"
            />
        </div>
    </div>
</template>
