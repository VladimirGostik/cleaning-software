<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AddIcon from '~icons/material-symbols/add-outline-rounded';
import ArrowUpIcon from '~icons/material-symbols/arrow-upward-outline-rounded';
import ArrowDownIcon from '~icons/material-symbols/arrow-downward-outline-rounded';
import DeleteIcon from '~icons/material-symbols/delete-outline-rounded';

import SideDrawer from '@/Components/SideDrawer.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import NumberInput from '@/Components/Forms/NumberInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { CURRENCY_SYMBOL, moveRow, newVisitItemRow, toVisitItemsPayload, type VisitItemRow } from '@/utils/workRecords';

interface VisitItemsFormData {
    rows: VisitItemRow[];
}

const props = defineProps<{
    open: boolean;
    objectId: string;
    items: App.Data.Objects.ObjectVisitItemData[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const form = useForm<VisitItemsFormData>({ rows: [] });

form.transform((data: VisitItemsFormData) => ({ items: toVisitItemsPayload(data.rows) }));

const list = ref<HTMLElement | null>(null);
const addButton = ref<HTMLElement | null>(null);

const errors = computed(() => form.errors as Record<string, string | undefined>);

watch(
    () => props.open,
    (open) => {
        if (!open) return;

        form.rows = props.items.map((item) => newVisitItemRow(item));
        form.clearErrors();
    },
    { immediate: true },
);

function focusRowName(index: number): void {
    const row = list.value?.querySelectorAll('li')[index];

    if (row) row.querySelector<HTMLInputElement>('input[type="text"]')?.focus();
    else addButton.value?.focus();
}

async function addRow(): Promise<void> {
    form.rows = [...form.rows, newVisitItemRow()];
    await nextTick();
    focusRowName(form.rows.length - 1);
}

async function removeRow(index: number): Promise<void> {
    form.rows = form.rows.filter((_, i) => i !== index);
    await nextTick();
    focusRowName(Math.min(index, form.rows.length - 1));
}

function move(index: number, delta: -1 | 1): void {
    form.rows = moveRow(form.rows, index, delta);
}

function submit(): void {
    form.submit('put', `/objects/${props.objectId}/visit-items`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <SideDrawer :open="open" :title="t('object_visit_items_edit')" @close="emit('close')">
        <FormProvider :form="form">
            <form novalidate class="flex h-full flex-col" @submit.prevent="submit">
                <div class="flex-1 space-y-4 p-6">
                    <p class="text-xs text-muted">{{ t('object_visit_items_hint') }}</p>

                    <p v-if="errors.items" class="text-sm text-error" role="alert">{{ errors.items }}</p>

                    <ul ref="list" class="space-y-4">
                        <li v-for="(row, index) in form.rows" :key="row.key" class="flex flex-wrap items-start gap-2">
                            <div class="min-w-48 flex-1 basis-48">
                                <TextInput
                                    v-model="row.name"
                                    :label="t('object_visit_item_name')"
                                    :aria-label="t('object_visit_item_name_row', { n: index + 1 })"
                                    :error="errors[`items.${index}.name`]"
                                />
                            </div>

                            <div class="w-36">
                                <NumberInput
                                    v-model="row.price"
                                    :label="t('object_visit_item_price')"
                                    :aria-label="t('object_visit_item_price_row', { n: index + 1 })"
                                    :suffix="CURRENCY_SYMBOL"
                                    :min="0"
                                    step="any"
                                    :error="errors[`items.${index}.price`]"
                                />
                            </div>

                            <fieldset class="fieldset">
                                <legend class="fieldset-legend" aria-hidden="true">&nbsp;</legend>
                                <div class="flex gap-1">
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-square"
                                        :disabled="index === 0"
                                        :aria-label="t('object_visit_item_move_up')"
                                        @click="move(index, -1)"
                                    >
                                        <ArrowUpIcon />
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-square"
                                        :disabled="index === form.rows.length - 1"
                                        :aria-label="t('object_visit_item_move_down')"
                                        @click="move(index, 1)"
                                    >
                                        <ArrowDownIcon />
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-square"
                                        :aria-label="t('object_visit_item_remove')"
                                        @click="removeRow(index)"
                                    >
                                        <DeleteIcon />
                                    </button>
                                </div>
                            </fieldset>

                            <p v-if="errors[`items.${index}.id`]" class="w-full text-sm text-error" role="alert">
                                {{ errors[`items.${index}.id`] }}
                            </p>
                        </li>
                    </ul>

                    <button ref="addButton" type="button" class="btn btn-neutral btn-sm" @click="addRow">
                        <AddIcon />
                        {{ t('object_visit_item_add') }}
                    </button>
                </div>

                <div class="sticky bottom-0 bg-base-100 px-6 py-3">
                    <FormActions :processing="form.processing" @cancel="emit('close')" />
                </div>
            </form>
        </FormProvider>
    </SideDrawer>
</template>
