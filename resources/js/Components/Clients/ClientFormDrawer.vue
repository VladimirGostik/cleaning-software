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
        type: (props.client?.type ?? 'corporate') as App.Enums.ClientType,
        name: props.client?.name ?? '',
        ico: props.client?.ico ?? '',
        dic: props.client?.dic ?? '',
        vat_number: props.client?.vat_number ?? '',
        is_vat_payer: props.client?.is_vat_payer ?? false,
        email: props.client?.email ?? '',
        phone: props.client?.phone ?? '',
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
            <header class="sticky top-0 bg-base-100 border-b border-base-300 px-6 py-4 flex justify-between items-center">
                <h2 class="text-lg font-semibold">
                    {{ t(isEdit ? 'clients.form.edit_title' : 'clients.form.create_title') }}
                </h2>
                <button class="btn btn-sm btn-ghost btn-circle" type="button" @click="emit('close')">
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </header>

            <form class="p-6 space-y-6 pb-24 flex-1" @submit.prevent="submit">
                <!-- Typ klienta -->
                <section>
                    <p class="label-text font-medium mb-2">{{ t('clients.form.section.type') }}</p>
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
                    <p class="label-text font-medium mb-3">{{ t('clients.form.section.basic') }}</p>
                    <div class="space-y-3">
                        <label class="form-control w-full">
                            <span class="label-text">
                                {{ t('clients.form.name') }} <span class="text-error">*</span>
                            </span>
                            <input
                                v-model="form.name"
                                type="text"
                                class="input input-bordered"
                                :class="{ 'input-error': form.errors.name }"
                            />
                            <span v-if="form.errors.name" class="text-error text-xs mt-1">
                                {{ form.errors.name }}
                            </span>
                        </label>

                        <label v-if="form.type === 'corporate'" class="form-control w-full">
                            <span class="label-text">
                                {{ t('clients.form.ico') }} <span class="text-error">*</span>
                            </span>
                            <input
                                v-model="form.ico"
                                type="text"
                                class="input input-bordered"
                                :class="{ 'input-error': form.errors.ico }"
                            />
                            <span v-if="form.errors.ico" class="text-error text-xs mt-1">
                                {{ form.errors.ico }}
                            </span>
                        </label>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="form-control w-full">
                                <span class="label-text">{{ t('clients.form.dic') }}</span>
                                <input
                                    v-model="form.dic"
                                    type="text"
                                    class="input input-bordered"
                                    :class="{ 'input-error': form.errors.dic }"
                                />
                                <span v-if="form.errors.dic" class="text-error text-xs mt-1">
                                    {{ form.errors.dic }}
                                </span>
                            </label>

                            <label class="form-control w-full">
                                <span class="label-text">{{ t('clients.form.ic_dph') }}</span>
                                <input
                                    v-model="form.vat_number"
                                    type="text"
                                    class="input input-bordered"
                                    :class="{ 'input-error': form.errors.vat_number }"
                                />
                                <span v-if="form.errors.vat_number" class="text-error text-xs mt-1">
                                    {{ form.errors.vat_number }}
                                </span>
                            </label>
                        </div>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input v-model="form.is_vat_payer" type="checkbox" class="toggle toggle-primary" />
                            <span class="label-text">{{ t('clients.form.is_vat_payer') }}</span>
                        </label>
                    </div>
                </section>

                <!-- Adresa -->
                <section>
                    <p class="label-text font-medium mb-3">{{ t('clients.form.section.address') }}</p>
                    <div class="space-y-3">
                        <label class="form-control w-full">
                            <span class="label-text">{{ t('clients.form.street') }}</span>
                            <input
                                v-model="form.street"
                                type="text"
                                class="input input-bordered"
                                :class="{ 'input-error': form.errors.street }"
                            />
                            <span v-if="form.errors.street" class="text-error text-xs mt-1">
                                {{ form.errors.street }}
                            </span>
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="form-control w-full">
                                <span class="label-text">{{ t('clients.form.city') }}</span>
                                <input
                                    v-model="form.city"
                                    type="text"
                                    class="input input-bordered"
                                    :class="{ 'input-error': form.errors.city }"
                                />
                                <span v-if="form.errors.city" class="text-error text-xs mt-1">
                                    {{ form.errors.city }}
                                </span>
                            </label>

                            <label class="form-control w-full">
                                <span class="label-text">{{ t('clients.form.postal_code') }}</span>
                                <input
                                    v-model="form.postal_code"
                                    type="text"
                                    class="input input-bordered"
                                    :class="{ 'input-error': form.errors.postal_code }"
                                />
                                <span v-if="form.errors.postal_code" class="text-error text-xs mt-1">
                                    {{ form.errors.postal_code }}
                                </span>
                            </label>
                        </div>

                        <label class="form-control w-full">
                            <span class="label-text">{{ t('clients.form.country') }}</span>
                            <input
                                v-model="form.country"
                                type="text"
                                class="input input-bordered"
                                :class="{ 'input-error': form.errors.country }"
                            />
                            <span v-if="form.errors.country" class="text-error text-xs mt-1">
                                {{ form.errors.country }}
                            </span>
                        </label>
                    </div>
                </section>

                <!-- Kontakt -->
                <section>
                    <p class="label-text font-medium mb-3">{{ t('clients.form.section.contact') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <label class="form-control w-full">
                            <span class="label-text">{{ t('clients.form.email') }}</span>
                            <input
                                v-model="form.email"
                                type="email"
                                class="input input-bordered"
                                :class="{ 'input-error': form.errors.email }"
                            />
                            <span v-if="form.errors.email" class="text-error text-xs mt-1">
                                {{ form.errors.email }}
                            </span>
                        </label>

                        <label class="form-control w-full">
                            <span class="label-text">{{ t('clients.form.phone') }}</span>
                            <input
                                v-model="form.phone"
                                type="tel"
                                class="input input-bordered"
                                :class="{ 'input-error': form.errors.phone }"
                            />
                            <span v-if="form.errors.phone" class="text-error text-xs mt-1">
                                {{ form.errors.phone }}
                            </span>
                        </label>
                    </div>
                </section>

                <!-- Kontaktne osoby -->
                <section>
                    <p class="label-text font-medium mb-3">{{ t('clients.form.section.contacts') }}</p>
                    <ContactsListField v-model="form.contacts" :errors="form.errors" />
                </section>

                <!-- Poznamka -->
                <section>
                    <p class="label-text font-medium mb-2">{{ t('clients.form.section.note') }}</p>
                    <textarea
                        v-model="form.note"
                        class="textarea textarea-bordered w-full"
                        rows="3"
                    />
                    <span v-if="form.errors.note" class="text-error text-xs mt-1">
                        {{ form.errors.note }}
                    </span>
                </section>
            </form>

            <footer
                class="sticky bottom-0 bg-base-100 border-t border-base-300 px-6 py-3 flex justify-end gap-2"
            >
                <button type="button" class="btn btn-ghost" @click="emit('close')">
                    {{ t('clients.form.cancel') }}
                </button>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                    {{ t('clients.form.save') }}
                </button>
            </footer>
        </aside>
    </Teleport>
</template>
