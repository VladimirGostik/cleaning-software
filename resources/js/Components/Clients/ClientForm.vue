<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import RadioGroup from '@/Components/Forms/RadioGroup.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import ContactsListField from './ContactsListField.vue';

import { CLIENT_TYPES, clientTypeKey } from '@/utils/enums';
import type { RadioOption } from '@/Components/Forms/RadioGroup.vue';

interface ClientFormData {
    type: App.Enums.ClientTypeEnum;
    name: string;
    ico: string;
    dic: string;
    vat_number: string;
    is_vat_payer: boolean;
    street: string;
    city: string;
    postal_code: string;
    country: string;
    note: string;
    contacts: App.Data.Clients.ClientContactData[];
}

const props = defineProps<{
    client?: App.Data.Clients.ClientDetailData | null;
}>();

const emit = defineEmits<{
    saved: [];
    cancel: [];
}>();

const { t } = useI18n();

const isEdit = computed(() => !!props.client);

const form = useForm<ClientFormData>(
    isEdit.value ? 'put' : 'post',
    isEdit.value ? `/clients/${props.client!.id}` : '/clients',
    {
        type: props.client?.type ?? 'corporate',
        name: props.client?.name ?? '',
        ico: props.client?.ico ?? '',
        dic: props.client?.dic ?? '',
        vat_number: props.client?.vat_number ?? '',
        is_vat_payer: props.client?.is_vat_payer ?? false,
        street: props.client?.street ?? '',
        city: props.client?.city ?? '',
        postal_code: props.client?.postal_code ?? '',
        country: props.client?.country ?? 'SK',
        note: props.client?.note ?? '',
        contacts: (props.client?.contacts ?? []).map((c) => ({ ...c })),
    },
);

const typeOptions = computed<RadioOption[]>(() => CLIENT_TYPES.map((v) => ({ value: v, label: t(clientTypeKey(v)) })));

function submit(): void {
    form.submit({ preserveScroll: true, onSuccess: () => emit('saved') });
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="flex h-full flex-col" @submit.prevent="submit">
            <div class="flex-1 space-y-6 p-6">
                <RadioGroup field="type" :label="t('client_type')" :options="typeOptions" required />

                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-base-content/70">{{ t('basic_info') }}</h2>

                    <TextInput field="name" :label="t('client_name')" required autofocus />

                    <TextInput field="ico" :label="t('client_ico')" :required="form.type === 'corporate'" />

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <TextInput field="dic" :label="t('client_dic')" />
                        <TextInput field="vat_number" :label="t('client_vat_number')" />
                    </div>

                    <ToggleInput field="is_vat_payer" :label="t('client_is_vat_payer')" />
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
                    <h2 class="text-sm font-semibold text-base-content/70">{{ t('client_contacts') }}</h2>
                    <ContactsListField field="contacts" />
                </div>

                <TextareaInput v-model="form.note" :label="t('note')" :error="form.errors.note" :rows="4" />
            </div>

            <div class="sticky bottom-0 border-t border-base-300 bg-base-100 px-6 py-3">
                <FormActions :processing="form.processing" @cancel="emit('cancel')" />
            </div>
        </form>
    </FormProvider>
</template>
