<script setup lang="ts">
    import { Link, useForm } from '@inertiajs/vue3';
    import { computed } from 'vue';
    import GuestLayout from '@/Layouts/GuestLayout.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';

    const pageProps = usePageProps();
    const { t } = useTranslate();

    const status = computed<string | null>(() => pageProps.flash?.status ?? pageProps.flash?.success);

    const form = useForm({ email: '' });

    function submit() {
        form.post('/forgot-password');
    }
</script>

<template>
    <GuestLayout>
        <h2 class="card-title text-2xl mb-4">{{ t('reset_password') }}</h2>

        <div v-if="status" class="alert alert-success text-sm">{{ status }}</div>

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

            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                {{ t('send_reset_link') }}
            </button>

            <Link href="/login" class="link link-hover text-sm text-center">{{ t('login') }}</Link>
        </form>
    </GuestLayout>
</template>
