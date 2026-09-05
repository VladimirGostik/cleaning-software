<script setup lang="ts">
    import { computed } from 'vue';
    import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';

    const props = defineProps<{
        search: string;
        type: App.Enums.ObjectTypeEnum | undefined;
        clientId: string | undefined;
        isActive: boolean | undefined;
        types: Array<{ value: string; label: string }>;
        clients: Array<{ id: string; name: string }>;
    }>();

    const emit = defineEmits<{
        (e: 'update:search', v: string): void;
        (e: 'update:type', v: App.Enums.ObjectTypeEnum | undefined): void;
        (e: 'update:clientId', v: string | undefined): void;
        (e: 'update:isActive', v: boolean | undefined): void;
    }>();

    const { t } = useTranslate();

    const typeValue = computed<string>({
        get: () => props.type ?? '',
        set: (val: string | number) => {
            const str = String(val);
            emit('update:type', str ? (str as App.Enums.ObjectTypeEnum) : undefined);
        },
    });

    const clientValue = computed<string>({
        get: () => props.clientId ?? '',
        set: (val: string | number) => {
            const str = String(val);
            emit('update:clientId', str || undefined);
        },
    });

    const activeValue = computed<string>({
        get: () => (props.isActive === undefined ? '' : String(props.isActive)),
        set: (val: string | number) => {
            const str = String(val);
            if (str === '') {
                emit('update:isActive', undefined);
            } else {
                emit('update:isActive', str === 'true');
            }
        },
    });

    const typeOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('objects.filter.all_types') },
        ...props.types.map((opt) => ({ value: opt.value, label: opt.label })),
    ]);

    const clientOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('objects.filter.all_clients') },
        ...props.clients.map((c) => ({ value: c.id, label: c.name })),
    ]);

    const activeOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('objects.filter.all_states') },
        { value: 'true', label: t('objects.filter.active') },
        { value: 'false', label: t('objects.filter.inactive') },
    ]);
</script>

<template>
    <div class="flex flex-wrap gap-3 mb-4 items-end">
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

        <div class="flex-1 min-w-44">
            <SelectInput v-model="typeValue" :options="typeOptions" :label="t('objects.col.type')" />
        </div>

        <div v-if="clients.length > 0" class="flex-1 min-w-44">
            <SelectInput v-model="clientValue" :options="clientOptions" :label="t('objects.col.client')" />
        </div>

        <div class="flex-1 min-w-44">
            <SelectInput v-model="activeValue" :options="activeOptions" :label="t('objects.col.active')" />
        </div>
    </div>
</template>
