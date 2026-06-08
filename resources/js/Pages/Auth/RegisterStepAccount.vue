<script setup lang="ts">
    /* eslint-disable vue/no-mutating-props -- InertiaForm is an intentionally mutable reactive object; step components bind to it directly */

    import { ref } from 'vue';
    import type { InertiaForm } from '@inertiajs/vue3';
    import { EyeIcon, EyeSlashIcon, CheckIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import PasswordStrengthBar from '@/Pages/Auth/PasswordStrengthBar.vue';
    import type { RegisterFormData } from '@/Pages/Auth/Register.vue';

    defineProps<{
        form: InertiaForm<RegisterFormData>;
    }>();

    const emit = defineEmits<{
        (e: 'next'): void;
    }>();

    const { t } = useTranslate();

    // eslint-disable-next-line no-restricted-syntax -- imperative DOM toggle: password visibility
    const showPassword = ref(false);
    // eslint-disable-next-line no-restricted-syntax -- imperative DOM toggle: confirm password visibility
    const showConfirm = ref(false);
</script>

<template>
    <div class="flex flex-col gap-[18px]">
        <div class="mb-2">
            <h2 class="text-[22px] font-bold text-slate-900">{{ t('register.step1_title') }}</h2>
        </div>

        <!-- Name -->
        <div class="flex flex-col gap-1.5">
            <label for="reg-name" class="text-sm font-medium text-slate-700">{{ t('profile') }}</label>
            <input
                id="reg-name"
                v-model="form.name"
                type="text"
                autocomplete="name"
                class="w-full rounded-lg border border-slate-200 bg-white py-2.5 px-3 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-100': form.errors.name }"
            />
            <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
        </div>

        <!-- Email -->
        <div class="flex flex-col gap-1.5">
            <label for="reg-email" class="text-sm font-medium text-slate-700">{{ t('email') }}</label>
            <input
                id="reg-email"
                v-model="form.email"
                type="email"
                autocomplete="email"
                class="w-full rounded-lg border border-slate-200 bg-white py-2.5 px-3 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-100': form.errors.email }"
            />
            <p v-if="form.errors.email" class="text-xs text-red-500">{{ form.errors.email }}</p>
        </div>

        <!-- Password -->
        <div class="flex flex-col gap-1.5">
            <label for="reg-password" class="text-sm font-medium text-slate-700">{{ t('password') }}</label>
            <div class="relative">
                <input
                    id="reg-password"
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                    :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-100': form.errors.password }"
                />
                <button
                    type="button"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600"
                    :aria-label="showPassword ? t('auth.toggle_password_hide') : t('auth.toggle_password_show')"
                    @click="showPassword = !showPassword"
                >
                    <EyeSlashIcon v-if="showPassword" class="h-4 w-4" />
                    <EyeIcon v-else class="h-4 w-4" />
                </button>
            </div>
            <PasswordStrengthBar :password="form.password" />
            <p v-if="form.errors.password" class="text-xs text-red-500">{{ form.errors.password }}</p>
        </div>

        <!-- Confirm password -->
        <div class="flex flex-col gap-1.5">
            <label for="reg-confirm" class="text-sm font-medium text-slate-700">
                {{ t('password_confirmation') }}
            </label>
            <div class="relative">
                <input
                    id="reg-confirm"
                    v-model="form.password_confirmation"
                    :type="showConfirm ? 'text' : 'password'"
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                    :class="{
                        'border-red-400 focus:border-red-400 focus:ring-red-100':
                            form.errors.password_confirmation ||
                            (form.password !== form.password_confirmation &&
                                form.password_confirmation.length > 0),
                    }"
                />
                <button
                    type="button"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600"
                    :aria-label="showConfirm ? t('auth.toggle_password_hide') : t('auth.toggle_password_show')"
                    @click="showConfirm = !showConfirm"
                >
                    <EyeSlashIcon v-if="showConfirm" class="h-4 w-4" />
                    <EyeIcon v-else class="h-4 w-4" />
                </button>
            </div>
            <p
                v-if="
                    form.password !== form.password_confirmation && form.password_confirmation.length > 0
                "
                class="text-xs text-red-500"
            >
                {{ t('register.password_match_hint') }}
            </p>
        </div>

        <!-- Terms -->
        <label class="flex cursor-pointer items-start gap-2.5">
            <span
                class="mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded border transition"
                :class="form.terms_accepted ? 'auth-checkbox-checked' : 'auth-checkbox-unchecked'"
            >
                <CheckIcon v-if="form.terms_accepted" class="h-3 w-3" />
            </span>
            <input v-model="form.terms_accepted" type="checkbox" class="sr-only" />
            <span class="text-[13px] text-slate-700">{{ t('register.terms_accept') }}</span>
        </label>
        <p v-if="form.errors.terms_accepted" class="text-xs text-red-500">
            {{ form.errors.terms_accepted }}
        </p>

        <!-- Next -->
        <button
            type="button"
            class="flex w-full items-center justify-center rounded-lg py-2.5 text-sm font-semibold text-white transition auth-submit-btn disabled:opacity-50"
            :disabled="
                !form.name.trim() ||
                !form.email.trim() ||
                !form.password ||
                !form.password_confirmation ||
                form.password !== form.password_confirmation ||
                !form.terms_accepted
            "
            @click="emit('next')"
        >
            {{ t('register.next') }}
        </button>
    </div>
</template>
