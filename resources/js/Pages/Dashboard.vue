<script setup lang="ts">
    import { computed } from 'vue';
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';

    const pageProps = usePageProps();
    const { t } = useTranslate();

    const user = computed(() => pageProps.auth?.user);
    const tenant = computed(() => pageProps.tenant?.active);
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto">
            <h1 class="text-3xl font-bold mb-2">{{ t('dashboard') }}</h1>
            <p class="text-base-content/60 mb-6">
                <span v-if="tenant">{{ tenant.name }} • </span>
                <span v-if="user">{{ user.email }}</span>
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('nav.schedule') }}</h2>
                        <p class="text-3xl font-bold">—</p>
                        <p class="text-sm text-base-content/60">{{ t('tagline') }}</p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('nav.invoices') }}</h2>
                        <p class="text-3xl font-bold">—</p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('nav.contracts') }}</h2>
                        <p class="text-3xl font-bold">—</p>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-6">
                <span>
                    Base scaffold ready. Domain modules (Clients, Objects, Quotes, Contracts, Schedule,
                    Invoices) will be added next via <code>/feature</code>.
                </span>
            </div>
        </div>
    </AppLayout>
</template>
