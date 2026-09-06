<script setup lang="ts">
import { computed, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

import TextInput from '@/Components/Forms/TextInput.vue';
import { useFormContext } from '@/Components/Forms/useFormContext';
import { callValidate } from '@/Components/Forms/useFieldError';

type ClientContactData = App.Data.Clients.ClientContactData;
// Mutable view over the (readonly) DTO shape — the form array is mutated in place.
type MutableContact = { -readonly [K in keyof ClientContactData]: ClientContactData[K] };

const props = defineProps<{
    field: string;
}>();

const { t } = useI18n();
const uid = useId();
const form = useFormContext();

if (import.meta.env.DEV && !form) {
    console.warn(
        `[ContactsListField] field="${props.field}" is set but no <FormProvider> was found in the component tree.`,
    );
}

const rows = computed<MutableContact[]>(() => {
    if (!form) return [];
    return (form as Record<string, unknown>)[props.field] as MutableContact[];
});

const errors = computed(() => (form ? (form.errors as Record<string, string | undefined>) : {}));

// Stable row identity across splices — never leaks into the submitted payload.
const rowIds = new WeakMap<object, number>();
let rowIdCounter = 0;

function rowKey(row: MutableContact): number {
    let id = rowIds.get(row);
    if (id === undefined) {
        id = ++rowIdCounter;
        rowIds.set(row, id);
    }
    return id;
}

function add(): void {
    if (!form) return;
    rows.value.push({
        id: null,
        name: '',
        position: null,
        email: null,
        phone: null,
        is_primary: rows.value.length === 0,
    });
}

function remove(index: number): void {
    if (!form) return;
    const wasPrimary = rows.value[index]?.is_primary;
    rows.value.splice(index, 1);
    if (wasPrimary && rows.value.length > 0) {
        rows.value[0].is_primary = true;
    }
}

function setPrimary(index: number): void {
    if (!form) return;
    rows.value.forEach((row, i) => {
        row.is_primary = i === index;
    });
    callValidate(form, props.field);
}

function setField(index: number, key: 'name' | 'position' | 'email' | 'phone', value: string): void {
    if (!form) return;
    const row = rows.value[index];
    if (!row) return;
    const nextValue = key === 'name' ? value : value === '' ? null : value;
    // Indexed write across a union of key types is a known TS limitation (TS2322)
    // even though `key` and `nextValue` are correlated by the ternary above.
    (row as unknown as Record<string, string | null>)[key] = nextValue;
}
</script>

<template>
    <div class="space-y-3">
        <div v-for="(row, index) in rows" :key="rowKey(row)" class="card bg-base-200 p-3">
            <div class="mb-2 flex items-start justify-between">
                <label class="flex cursor-pointer items-center gap-2">
                    <input
                        type="radio"
                        :name="`${field}-primary-${uid}`"
                        class="radio radio-sm radio-primary"
                        :checked="row.is_primary"
                        @change="setPrimary(index)"
                    />
                    <span class="text-sm">{{ t('client_contact_is_primary') }}</span>
                </label>

                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :aria-label="t('client_contact_remove')"
                    @click="remove(index)"
                >
                    <TrashIcon class="size-4" />
                </button>
            </div>

            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <TextInput
                    :model-value="row.name"
                    :label="t('name')"
                    required
                    :error="errors[`${field}.${index}.name`]"
                    @update:model-value="setField(index, 'name', $event)"
                    @change="callValidate(form, `${field}.${index}.name`)"
                />

                <TextInput
                    :model-value="row.position ?? ''"
                    :label="t('position')"
                    :error="errors[`${field}.${index}.position`]"
                    @update:model-value="setField(index, 'position', $event)"
                    @change="callValidate(form, `${field}.${index}.position`)"
                />

                <TextInput
                    type="email"
                    :model-value="row.email ?? ''"
                    :label="t('email')"
                    :error="errors[`${field}.${index}.email`]"
                    @update:model-value="setField(index, 'email', $event)"
                    @change="callValidate(form, `${field}.${index}.email`)"
                />

                <TextInput
                    type="tel"
                    :model-value="row.phone ?? ''"
                    :label="t('phone')"
                    :error="errors[`${field}.${index}.phone`]"
                    @update:model-value="setField(index, 'phone', $event)"
                    @change="callValidate(form, `${field}.${index}.phone`)"
                />
            </div>
        </div>

        <p v-if="errors[field]" class="text-error text-sm">{{ errors[field] }}</p>

        <button type="button" class="btn btn-ghost btn-sm" @click="add">
            <PlusIcon class="size-4" />
            {{ t('client_contact_add') }}
        </button>
    </div>
</template>
