<script setup lang="ts">
    import { useForm } from '@inertiajs/vue3';
    import GuestLayout from '@/Layouts/GuestLayout.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = defineProps<{
        email: string | null;
        token: string;
    }>();

    const { t } = useTranslate();

    const form = useForm({
        token: props.token,
        email: props.email ?? '',
        password: '',
        password_confirmation: '',
    });

    function submit() {
        form.post('/reset-password', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }
</script>

<template>
    <GuestLayout>
        <h2 class="card-title text-2xl mb-4">{{ t('reset_password') }}</h2>

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
            </fieldset>

            <fieldset class="fieldset">
                <legend class="fieldset-legend">{{ t('password') }}</legend>
                <input
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="input w-full"
                    :class="{ 'input-error': form.errors.password }"
                />
                <p v-if="form.errors.password" class="text-error text-xs mt-1">{{ form.errors.password }}</p>
            </fieldset>

            <fieldset class="fieldset">
                <legend class="fieldset-legend">{{ t('password_confirmation') }}</legend>
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="input w-full"
                />
            </fieldset>

            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                {{ t('reset_password') }}
            </button>
        </form>
    </GuestLayout>
</template>
