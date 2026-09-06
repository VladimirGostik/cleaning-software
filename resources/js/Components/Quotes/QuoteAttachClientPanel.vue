<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';

const props = defineProps<{
    quoteId: string;
    clients: readonly App.Data.Clients.ClientOptionData[];
    objects: readonly App.Data.Objects.ObjectOptionData[];
}>();

const { t } = useI18n();

const form = useForm('post', `/quotes/${props.quoteId}/attach-client`, {
    client_id: '',
    cleaning_object_id: null as string | null,
});

const clientOptions = computed<SelectOption[]>(() => props.clients.map((c) => ({ value: c.id, label: c.name })));

const objectOptions = computed<SelectOption[]>(() =>
    props.objects
        .filter((o) => o.client_id === form.client_id && o.is_active)
        .map((o) => ({ value: o.id, label: o.name })),
);

watch(
    () => form.client_id,
    () => {
        form.cleaning_object_id = null;
    },
);

function submit(): void {
    form.submit();
}
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-3">
            <h2 class="card-title text-base">{{ t('quote_attach_client_title') }}</h2>
            <p class="text-sm text-base-content/60">{{ t('quote_attach_client_hint') }}</p>

            <FormProvider :form="form">
                <form novalidate class="space-y-3" @submit.prevent="submit">
                    <SelectInput
                        field="client_id"
                        :label="t('client')"
                        :options="clientOptions"
                        :placeholder="t('select_client')"
                        required
                    />

                    <SelectInput
                        field="cleaning_object_id"
                        :label="t('quote_object')"
                        :options="objectOptions"
                        :placeholder="t('invoice_select_object')"
                        :disabled="!form.client_id"
                    />

                    <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
                        <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                        {{ t('quote_attach_client_submit') }}
                    </button>
                </form>
            </FormProvider>
        </div>
    </div>
</template>
