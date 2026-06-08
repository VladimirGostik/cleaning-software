<script setup lang="ts">
    import { ref, useId } from 'vue';
    import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import TextInput from '@/Components/Forms/TextInput.vue';

    const props = defineProps<{
        modelValue: App.Data.Clients.ClientContactData[];
        errors: Record<string, string>;
    }>();

    const emit = defineEmits<{
        (e: 'update:modelValue', v: App.Data.Clients.ClientContactData[]): void;
    }>();

    const { t } = useTranslate();
    const uid = useId();

    // Single source of truth: local clone of the prop (no watch mirror).
    // All mutations operate here and immediately emit the full array.
    // eslint-disable-next-line no-restricted-syntax -- local mutable array for dynamic contact rows; not app/cross-component state
    const items = ref<App.Data.Clients.ClientContactData[]>(
        props.modelValue.map((c) => ({ ...c })),
    );

    function notify() {
        emit('update:modelValue', items.value.map((c) => ({ ...c })));
    }

    function add() {
        items.value.push({
            id: null,
            name: '',
            position: null,
            email: null,
            phone: null,
            is_primary: items.value.length === 0,
        });
        notify();
    }

    function remove(idx: number) {
        const wasPrimary = items.value[idx].is_primary;
        items.value.splice(idx, 1);
        if (wasPrimary && items.value.length > 0) {
            items.value[0].is_primary = true;
        }
        notify();
    }

    function setPrimary(idx: number) {
        items.value.forEach((item, i) => {
            item.is_primary = i === idx;
        });
        notify();
    }

    function onFieldChange(idx: number, field: keyof App.Data.Clients.ClientContactData, value: string) {
        (items.value[idx] as Record<string, unknown>)[field] = value === '' ? null : value;
        notify();
    }
</script>

<template>
    <div class="space-y-3">
        <div v-for="(c, i) in items" :key="i" class="card bg-base-200 p-3">
            <div class="flex justify-between items-start mb-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="radio"
                        :name="'primary-' + uid"
                        class="radio radio-sm"
                        :checked="c.is_primary"
                        @change="setPrimary(i)"
                    />
                    <span class="text-sm">{{ t('clients.form.contact.is_primary') }}</span>
                </label>
                <button type="button" class="btn btn-ghost btn-xs" @click="remove(i)">
                    <TrashIcon class="w-4 h-4" />
                    <span class="sr-only">{{ t('clients.form.contact.remove') }}</span>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <TextInput
                    :model-value="c.name ?? ''"
                    :placeholder="t('clients.form.contact.name')"
                    :aria-label="t('clients.form.contact.name')"
                    :error="errors[`contacts.${i}.name`]"
                    @update:model-value="onFieldChange(i, 'name', $event)"
                />
                <TextInput
                    :model-value="c.position ?? ''"
                    :placeholder="t('clients.form.contact.position')"
                    :aria-label="t('clients.form.contact.position')"
                    @update:model-value="onFieldChange(i, 'position', $event)"
                />
                <TextInput
                    type="email"
                    :model-value="c.email ?? ''"
                    :placeholder="t('clients.form.contact.email')"
                    :aria-label="t('clients.form.contact.email')"
                    @update:model-value="onFieldChange(i, 'email', $event)"
                />
                <TextInput
                    type="tel"
                    :model-value="c.phone ?? ''"
                    :placeholder="t('clients.form.contact.phone')"
                    :aria-label="t('clients.form.contact.phone')"
                    @update:model-value="onFieldChange(i, 'phone', $event)"
                />
            </div>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" @click="add">
            <PlusIcon class="w-4 h-4" />
            {{ t('clients.form.contact.add') }}
        </button>
    </div>
</template>
