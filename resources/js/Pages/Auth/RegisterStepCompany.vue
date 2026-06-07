<script setup lang="ts">
    /* eslint-disable vue/no-mutating-props -- InertiaForm is an intentionally mutable reactive object; step components bind to it directly */
    import { watch } from 'vue';
    import type { InertiaForm } from '@inertiajs/vue3';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { RegisterFormData } from '@/Pages/Auth/Register.vue';
    import type { useIcoLookup } from '@/Composables/useIcoLookup';

    const props = defineProps<{
        form: InertiaForm<RegisterFormData>;
        lookup: ReturnType<typeof useIcoLookup>;
    }>();

    const emit = defineEmits<{
        (e: 'next'): void;
        (e: 'back'): void;
    }>();

    const { t } = useTranslate();

    watch(
        () => props.lookup.data.value,
        (result) => {
            if (!result) {
                return;
            }
            props.form.company.name = result.name;
            props.form.company.dic = result.dic;
            props.form.company.vat_number = result.vat_number;
            props.form.company.address_line = result.address_line;
            props.form.company.city = result.city;
            props.form.company.postal_code = result.postal_code;
        },
    );
</script>

<template>
    <div class="flex flex-col gap-[18px]">
        <div class="mb-2">
            <h2 class="text-[22px] font-bold text-slate-900">{{ t('register.step2_title') }}</h2>
        </div>

        <!-- IČO -->
        <div class="flex flex-col gap-1.5">
            <label for="reg-ico" class="text-sm font-medium text-slate-700">{{ t('register.ico') }}</label>
            <div class="relative">
                <input
                    id="reg-ico"
                    v-model="form.company.ico"
                    type="text"
                    inputmode="numeric"
                    maxlength="8"
                    class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                    :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-100': form.errors['company.ico'] }"
                    @input="lookup.search(form.company.ico)"
                />
                <span
                    v-if="lookup.loading.value"
                    class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600"
                />
            </div>
            <p v-if="form.errors['company.ico']" class="text-xs text-red-500">
                {{ form.errors['company.ico'] }}
            </p>
            <p v-if="lookup.error.value" class="text-xs text-amber-600">
                {{ t('register.ico_lookup_error') }}
            </p>
        </div>

        <!-- Company name -->
        <div class="flex flex-col gap-1.5">
            <label for="reg-company-name" class="text-sm font-medium text-slate-700">
                {{ t('register.company_name') }}
            </label>
            <input
                id="reg-company-name"
                v-model="form.company.name"
                type="text"
                class="w-full rounded-lg border border-slate-200 bg-white py-2.5 px-3 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-100': form.errors['company.name'] }"
            />
            <p v-if="form.errors['company.name']" class="text-xs text-red-500">
                {{ form.errors['company.name'] }}
            </p>
        </div>

        <!-- DIČ -->
        <div class="flex flex-col gap-1.5">
            <label for="reg-dic" class="text-sm font-medium text-slate-700">{{ t('register.dic') }}</label>
            <input
                id="reg-dic"
                v-model="form.company.dic"
                type="text"
                class="w-full rounded-lg border border-slate-200 bg-white py-2.5 px-3 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-100': form.errors['company.dic'] }"
            />
            <p v-if="form.errors['company.dic']" class="text-xs text-red-500">
                {{ form.errors['company.dic'] }}
            </p>
        </div>

        <!-- DPH toggle -->
        <label class="flex cursor-pointer items-center gap-3">
            <input v-model="form.company.is_vat_payer" type="checkbox" class="sr-only" />
            <span
                class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded border transition"
                :class="form.company.is_vat_payer ? 'auth-checkbox-checked' : 'auth-checkbox-unchecked'"
            >
                <svg
                    v-if="form.company.is_vat_payer"
                    class="h-3 w-3"
                    viewBox="0 0 12 12"
                    fill="none"
                >
                    <path
                        d="M2 6l3 3 5-5"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </span>
            <span class="text-[13px] text-slate-700">{{ t('register.is_vat_payer') }}</span>
        </label>

        <!-- IČ DPH -->
        <div v-if="form.company.is_vat_payer" class="flex flex-col gap-1.5">
            <label for="reg-vat" class="text-sm font-medium text-slate-700">
                {{ t('register.vat_number') }}
            </label>
            <input
                id="reg-vat"
                v-model="form.company.vat_number"
                type="text"
                class="w-full rounded-lg border border-slate-200 bg-white py-2.5 px-3 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                :class="{
                    'border-red-400 focus:border-red-400 focus:ring-red-100': form.errors['company.vat_number'],
                }"
            />
            <p v-if="form.errors['company.vat_number']" class="text-xs text-red-500">
                {{ form.errors['company.vat_number'] }}
            </p>
        </div>

        <!-- Address -->
        <div class="flex flex-col gap-1.5">
            <label for="reg-address" class="text-sm font-medium text-slate-700">
                {{ t('register.address_line') }}
            </label>
            <input
                id="reg-address"
                v-model="form.company.address_line"
                type="text"
                class="w-full rounded-lg border border-slate-200 bg-white py-2.5 px-3 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                :class="{
                    'border-red-400 focus:border-red-400 focus:ring-red-100': form.errors['company.address_line'],
                }"
            />
            <p v-if="form.errors['company.address_line']" class="text-xs text-red-500">
                {{ form.errors['company.address_line'] }}
            </p>
        </div>

        <!-- City + Postal -->
        <div class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1.5">
                <label for="reg-city" class="text-sm font-medium text-slate-700">
                    {{ t('register.city') }}
                </label>
                <input
                    id="reg-city"
                    v-model="form.company.city"
                    type="text"
                    class="w-full rounded-lg border border-slate-200 bg-white py-2.5 px-3 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                    :class="{
                        'border-red-400 focus:border-red-400 focus:ring-red-100': form.errors['company.city'],
                    }"
                />
                <p v-if="form.errors['company.city']" class="text-xs text-red-500">
                    {{ form.errors['company.city'] }}
                </p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="reg-postal" class="text-sm font-medium text-slate-700">
                    {{ t('register.postal_code') }}
                </label>
                <input
                    id="reg-postal"
                    v-model="form.company.postal_code"
                    type="text"
                    class="w-full rounded-lg border border-slate-200 bg-white py-2.5 px-3 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                    :class="{
                        'border-red-400 focus:border-red-400 focus:ring-red-100':
                            form.errors['company.postal_code'],
                    }"
                />
                <p v-if="form.errors['company.postal_code']" class="text-xs text-red-500">
                    {{ form.errors['company.postal_code'] }}
                </p>
            </div>
        </div>

        <!-- Navigation -->
        <div class="flex gap-3 pt-1">
            <button
                type="button"
                class="flex-1 rounded-lg border border-slate-200 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                @click="emit('back')"
            >
                {{ t('register.back') }}
            </button>
            <button
                type="button"
                class="flex-1 flex items-center justify-center rounded-lg py-2.5 text-sm font-semibold text-white transition auth-submit-btn disabled:opacity-50"
                :disabled="
                    !form.company.name.trim() ||
                    !form.company.ico.trim() ||
                    !form.company.address_line.trim() ||
                    !form.company.city.trim() ||
                    !form.company.postal_code.trim()
                "
                @click="emit('next')"
            >
                {{ t('register.next') }}
            </button>
        </div>
    </div>
</template>
