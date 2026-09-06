<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import NumberInput from '@/Components/Forms/NumberInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';

import { OBJECT_TYPES, objectTypeKey } from '@/utils/enums';
import { objectToUpsertPayload, type ObjectFormData } from './objectPayload';
import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

const props = defineProps<{
    object?: App.Data.Objects.ObjectDetailData | null;
    clients: App.Data.Clients.ClientOptionData[];
}>();

const emit = defineEmits<{
    saved: [];
    cancel: [];
}>();

const { t } = useI18n();

const isEdit = computed(() => !!props.object);

const form = useForm<ObjectFormData>(
    isEdit.value ? 'put' : 'post',
    isEdit.value ? `/objects/${props.object!.id}` : '/objects',
    isEdit.value
        ? objectToUpsertPayload(props.object!)
        : {
              client_id: props.clients.length === 1 ? props.clients[0].id : '',
              type: 'office',
              name: '',
              street: '',
              city: '',
              postal_code: '',
              country: 'SK',
              access_code: '',
              key_box_code: '',
              key_count: null,
              special_instructions: '',
              area_sqm: null,
              floor: null,
              is_active: true,
          },
);

const clientOptions = computed<SelectOption[]>(() => props.clients.map((c) => ({ value: c.id, label: c.name })));

const typeOptions = computed<SelectOption[]>(() => OBJECT_TYPES.map((v) => ({ value: v, label: t(objectTypeKey(v)) })));

function submit(): void {
    form.submit({ preserveScroll: true, onSuccess: () => emit('saved') });
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="flex h-full flex-col" @submit.prevent="submit">
            <div class="flex-1 space-y-6 p-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <SelectInput
                        field="client_id"
                        :label="t('client')"
                        :options="clientOptions"
                        :placeholder="t('select_client')"
                        required
                    />

                    <SelectInput
                        field="type"
                        :label="t('object_type')"
                        :options="typeOptions"
                        :placeholder="t('select_type')"
                        required
                    />
                </div>

                <TextInput field="name" :label="t('object_name')" required autofocus />

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <NumberInput
                        v-model="form.area_sqm"
                        :label="t('object_area_sqm')"
                        :min="0"
                        step="any"
                        :error="form.errors.area_sqm"
                    />

                    <NumberInput v-model="form.floor" :label="t('object_floor')" :error="form.errors.floor" />
                </div>

                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-base-content/70">{{ t('address') }}</h2>

                    <TextInput field="street" :label="t('street')" />

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <TextInput field="city" :label="t('city')" />
                        <TextInput field="postal_code" :label="t('postal_code')" />
                    </div>

                    <TextInput field="country" :label="t('country')" />
                </div>

                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-base-content/70">{{ t('object_access') }}</h2>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <TextInput field="access_code" :label="t('object_access_code')" />
                        <TextInput field="key_box_code" :label="t('object_key_box_code')" />
                    </div>

                    <NumberInput
                        v-model="form.key_count"
                        :label="t('object_key_count')"
                        :min="0"
                        :error="form.errors.key_count"
                    />
                </div>

                <TextareaInput
                    v-model="form.special_instructions"
                    :label="t('object_special_instructions')"
                    :error="form.errors.special_instructions"
                    :rows="4"
                />

                <ToggleInput v-if="isEdit" field="is_active" :label="t('object_is_active')" />
            </div>

            <div class="sticky bottom-0 border-t border-base-300 bg-base-100 px-6 py-3">
                <FormActions :processing="form.processing" @cancel="emit('cancel')" />
            </div>
        </form>
    </FormProvider>
</template>
