<script setup lang="ts">
    import { computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = defineProps<{
        quoteId: string;
        clients: App.Data.Clients.ClientOptionData[];
        objects: App.Data.Objects.ObjectOptionData[];
    }>();

    const { t } = useTranslate();

    const form = useForm('post', `/quotes/${props.quoteId}/attach-client`, {
        client_id: '',
        cleaning_object_id: null as string | null,
    });

    const clientOptions = computed<SelectOption[]>(() =>
        props.clients.map((c) => ({ value: c.id, label: c.name })),
    );

    const objectOptions = computed<SelectOption[]>(() =>
        props.objects
            .filter((o) => o.client_id === form.client_id)
            .map((o) => ({ value: o.id, label: o.name })),
    );

    function setClientId(value: string | number): void {
        form.client_id = String(value);
        form.cleaning_object_id = null;
    }

    function setObjectId(value: string | number): void {
        form.cleaning_object_id = String(value) || null;
    }

    function submit(): void {
        form.submit();
    }
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="card-title text-sm">{{ t('quotes.attach_client.title') }}</h2>
            <p class="text-xs text-base-content/60">{{ t('quotes.attach_client.hint') }}</p>

            <form novalidate class="space-y-3" @submit.prevent="submit">
                <SelectInput
                    :model-value="form.client_id"
                    :options="clientOptions"
                    :label="t('quotes.attach_client.client')"
                    :error="form.errors.client_id"
                    :placeholder="t('quotes.subject.client_placeholder')"
                    required
                    @update:model-value="setClientId"
                />

                <SelectInput
                    :model-value="form.cleaning_object_id ?? ''"
                    :options="objectOptions"
                    :label="t('quotes.attach_client.object')"
                    :error="form.errors.cleaning_object_id"
                    :placeholder="t('quotes.subject.object_placeholder')"
                    :disabled="!form.client_id"
                    @update:model-value="setObjectId"
                />

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
                        <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                        {{ t('quotes.attach_client.submit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
