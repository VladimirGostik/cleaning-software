<script setup lang="ts">
    import { computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import { XMarkIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import ContactsListField from '@/Components/Clients/ContactsListField.vue';

    const props = defineProps<{
        mode: 'create' | 'edit';
        client?: App.Data.Clients.ClientDetailData | null;
    }>();

    const emit = defineEmits<{
        (e: 'close'): void;
        (e: 'saved'): void;
    }>();

    const { t } = useTranslate();

    const isEdit = computed(() => props.mode === 'edit' && !!props.client);

    const form = useForm({
        type: (props.client?.type ?? 'corporate') as App.Enums.ClientTypeEnum,
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
    });

    function submit() {
        const opts = {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        };
        if (isEdit.value && props.client) {
            form.put(`/clients/${props.client.id}`, opts);
        } else {
            form.post('/clients', opts);
        }
    }
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-40 bg-black/40" @click="emit('close')" />
        <aside
            class="fixed top-0 right-0 z-50 h-full w-full md:w-[640px] bg-base-100 shadow-xl overflow-y-auto flex flex-col"
        >
            <header
                class="sticky top-0 bg-base-100 border-b border-base-300 px-6 py-4 flex justify-between items-center"
            >
                <h2 class="text-lg font-semibold">
                    {{ t(isEdit ? 'clients.form.edit_title' : 'clients.form.create_title') }}
                </h2>
                <button class="btn btn-sm btn-ghost btn-circle" type="button" @click="emit('close')">
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </header>

            <form id="client-form" class="p-6 space-y-6 pb-24 flex-1" @submit.prevent="submit">
                <!-- Typ klienta -->
                <section>
                    <p class="text-sm font-medium mb-2">{{ t('clients.form.section.type') }}</p>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.type" type="radio" class="radio" value="corporate" />
                            {{ t('client_type.corporate') }}
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.type" type="radio" class="radio" value="private" />
                            {{ t('client_type.private') }}
                        </label>
                    </div>
                </section>

                <!-- Zakladne udaje -->
                <section>
                    <p class="text-sm font-medium mb-3">{{ t('clients.form.section.basic') }}</p>
                    <div class="space-y-3">
                        <fieldset class="fieldset w-full">
                            <legend class="fieldset-legend">
                                {{ t('clients.form.name') }} <span class="text-error">*</span>
                            </legend>
                            <input
                                v-model="form.name"
                                type="text"
                                class="input w-full"
                                :class="{ 'input-error': form.errors.name }"
                            />
                            <span v-if="form.errors.name" class="fieldset-label text-error">
                                {{ form.errors.name }}
                            </span>
                        </fieldset>

                        <fieldset v-if="form.type === 'corporate'" class="fieldset w-full">
                            <legend class="fieldset-legend">
                                {{ t('clients.form.ico') }} <span class="text-error">*</span>
                            </legend>
                            <input
                                v-model="form.ico"
                                type="text"
                                class="input w-full"
                                :class="{ 'input-error': form.errors.ico }"
                            />
                            <span v-if="form.errors.ico" class="fieldset-label text-error">
                                {{ form.errors.ico }}
                            </span>
                        </fieldset>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <fieldset class="fieldset w-full">
                                <legend class="fieldset-legend">{{ t('clients.form.dic') }}</legend>
                                <input
                                    v-model="form.dic"
                                    type="text"
                                    class="input w-full"
                                    :class="{ 'input-error': form.errors.dic }"
                                />
                                <span v-if="form.errors.dic" class="fieldset-label text-error">
                                    {{ form.errors.dic }}
                                </span>
                            </fieldset>

                            <fieldset class="fieldset w-full">
                                <legend class="fieldset-legend">{{ t('clients.form.ic_dph') }}</legend>
                                <input
                                    v-model="form.vat_number"
                                    type="text"
                                    class="input w-full"
                                    :class="{ 'input-error': form.errors.vat_number }"
                                />
                                <span v-if="form.errors.vat_number" class="fieldset-label text-error">
                                    {{ form.errors.vat_number }}
                                </span>
                            </fieldset>
                        </div>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                v-model="form.is_vat_payer"
                                type="checkbox"
                                class="toggle toggle-primary"
                            />
                            <span class="text-sm">{{ t('clients.form.is_vat_payer') }}</span>
                        </label>
                    </div>
                </section>

                <!-- Adresa -->
                <section>
                    <p class="text-sm font-medium mb-3">{{ t('clients.form.section.address') }}</p>
                    <div class="space-y-3">
                        <fieldset class="fieldset w-full">
                            <legend class="fieldset-legend">{{ t('clients.form.street') }}</legend>
                            <input
                                v-model="form.street"
                                type="text"
                                class="input w-full"
                                :class="{ 'input-error': form.errors.street }"
                            />
                            <span v-if="form.errors.street" class="fieldset-label text-error">
                                {{ form.errors.street }}
                            </span>
                        </fieldset>

                        <div class="grid grid-cols-2 gap-3">
                            <fieldset class="fieldset w-full">
                                <legend class="fieldset-legend">{{ t('clients.form.city') }}</legend>
                                <input
                                    v-model="form.city"
                                    type="text"
                                    class="input w-full"
                                    :class="{ 'input-error': form.errors.city }"
                                />
                                <span v-if="form.errors.city" class="fieldset-label text-error">
                                    {{ form.errors.city }}
                                </span>
                            </fieldset>

                            <fieldset class="fieldset w-full">
                                <legend class="fieldset-legend">{{ t('clients.form.postal_code') }}</legend>
                                <input
                                    v-model="form.postal_code"
                                    type="text"
                                    class="input w-full"
                                    :class="{ 'input-error': form.errors.postal_code }"
                                />
                                <span v-if="form.errors.postal_code" class="fieldset-label text-error">
                                    {{ form.errors.postal_code }}
                                </span>
                            </fieldset>
                        </div>

                        <fieldset class="fieldset w-full">
                            <legend class="fieldset-legend">{{ t('clients.form.country') }}</legend>
                            <input
                                v-model="form.country"
                                type="text"
                                class="input w-full"
                                :class="{ 'input-error': form.errors.country }"
                            />
                            <span v-if="form.errors.country" class="fieldset-label text-error">
                                {{ form.errors.country }}
                            </span>
                        </fieldset>
                    </div>
                </section>

                <!-- Kontaktne osoby -->
                <section>
                    <p class="text-sm font-medium mb-3">{{ t('clients.form.section.contacts') }}</p>
                    <ContactsListField v-model="form.contacts" :errors="form.errors" />
                </section>

                <!-- Poznamka -->
                <section>
                    <fieldset class="fieldset w-full">
                        <legend class="fieldset-legend">{{ t('clients.form.section.note') }}</legend>
                        <textarea
                            v-model="form.note"
                            rows="4"
                            class="textarea w-full"
                            :class="{ 'textarea-error': form.errors.note }"
                        />
                        <span v-if="form.errors.note" class="fieldset-label text-error">
                            {{ form.errors.note }}
                        </span>
                    </fieldset>
                </section>
            </form>

            <footer
                class="sticky bottom-0 bg-base-100 border-t border-base-300 px-6 py-3 flex justify-end gap-2"
            >
                <button type="button" class="btn btn-ghost" @click="emit('close')">
                    {{ t('clients.form.cancel') }}
                </button>
                <button type="submit" form="client-form" class="btn btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                    {{ t('clients.form.save') }}
                </button>
            </footer>
        </aside>
    </Teleport>
</template>
