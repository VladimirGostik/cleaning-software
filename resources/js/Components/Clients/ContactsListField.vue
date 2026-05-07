<script setup lang="ts">
    import { reactive, watch, useId } from 'vue';
    import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = defineProps<{
        modelValue: App.Data.Clients.ClientContactData[];
        errors: Record<string, string>;
    }>();

    const emit = defineEmits<{
        (e: 'update:modelValue', v: App.Data.Clients.ClientContactData[]): void;
    }>();

    const { t } = useTranslate();
    const uid = useId();

    const items = reactive<App.Data.Clients.ClientContactData[]>(
        props.modelValue.map((c) => ({ ...c })),
    );

    watch(
        () => props.modelValue,
        (newVal) => {
            if (newVal.length !== items.length) {
                items.splice(0, items.length, ...newVal.map((c) => ({ ...c })));
            }
        },
    );

    function notifyParent() {
        emit('update:modelValue', [...items]);
    }

    function add() {
        items.push({
            id: null,
            name: '',
            position: null,
            email: null,
            phone: null,
            is_primary: items.length === 0,
        });
        notifyParent();
    }

    function remove(idx: number) {
        const wasPrimary = items[idx].is_primary;
        items.splice(idx, 1);
        if (wasPrimary && items.length > 0) {
            items[0].is_primary = true;
        }
        notifyParent();
    }

    function setPrimary(idx: number) {
        items.forEach((item, i) => {
            item.is_primary = i === idx;
        });
        notifyParent();
    }

    function onFieldChange() {
        notifyParent();
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
                <div>
                    <input
                        v-model="c.name"
                        :placeholder="t('clients.form.contact.name')"
                        class="input input-sm w-full"
                        :class="{ 'input-error': errors[`contacts.${i}.name`] }"
                        @input="onFieldChange"
                    />
                    <p v-if="errors[`contacts.${i}.name`]" class="text-error text-xs">
                        {{ errors[`contacts.${i}.name`] }}
                    </p>
                </div>
                <input
                    v-model="c.position"
                    :placeholder="t('clients.form.contact.position')"
                    class="input input-sm w-full"
                    @input="onFieldChange"
                />
                <input
                    v-model="c.email"
                    type="email"
                    :placeholder="t('clients.form.contact.email')"
                    class="input input-sm w-full"
                    @input="onFieldChange"
                />
                <input
                    v-model="c.phone"
                    type="tel"
                    :placeholder="t('clients.form.contact.phone')"
                    class="input input-sm w-full"
                    @input="onFieldChange"
                />
            </div>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" @click="add">
            <PlusIcon class="w-4 h-4" />
            {{ t('clients.form.contact.add') }}
        </button>
    </div>
</template>
