<script setup lang="ts">
    import { Link, useForm } from '@inertiajs/vue3';
    import { computed } from 'vue';
    import GuestLayout from '@/Layouts/GuestLayout.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';

    const props = usePageProps();
    const { t } = useTranslate();

    const canResetPassword = computed<boolean>(() => Boolean(props.canResetPassword));

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit() {
        form.post('/login', {
            onFinish: () => form.reset('password'),
        });
    }
</script>

<template>
    <GuestLayout>
        <h2 class="card-title text-2xl mb-4">{{ t('login') }}</h2>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <fieldset class="fieldset">
                <legend class="fieldset-legend">{{ t('email') }}</legend>
                <input
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    class="input w-full"
                    :class="{ 'input-error': form.errors.email }"
                />
                <p v-if="form.errors.email" class="text-error text-xs mt-1">{{ form.errors.email }}</p>
            </fieldset>

            <fieldset class="fieldset">
                <legend class="fieldset-legend">{{ t('password') }}</legend>
                <input
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="input w-full"
                    :class="{ 'input-error': form.errors.password }"
                />
                <p v-if="form.errors.password" class="text-error text-xs mt-1">{{ form.errors.password }}</p>
            </fieldset>

            <label class="label cursor-pointer justify-start gap-2 -mt-2">
                <input v-model="form.remember" type="checkbox" class="checkbox checkbox-sm" />
                <span class="label-text">{{ t('remember_me') }}</span>
            </label>

            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                {{ t('login') }}
            </button>

            <Link v-if="canResetPassword" href="/forgot-password" class="link link-hover text-sm text-center">
                {{ t('forgot_password') }}
            </Link>
        </form>
    </GuestLayout>
</template>
