<script setup lang="ts">
    import { computed } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = usePageProps();
    const { t } = useTranslate();

    const languages = computed(() => props.languages ?? []);
    const currentLocale = computed(() => props.locale ?? 'sk');

    function switchLocale(code: string) {
        router.get(`/language/${code}`, {}, { preserveState: false });
    }
</script>

<template>
    <div class="min-h-screen flex flex-col bg-base-200">
        <header class="navbar bg-base-100 shadow-sm">
            <div class="flex-1 px-4">
                <Link href="/" class="text-xl font-bold text-primary">{{ t('app_name') }}</Link>
            </div>
            <div class="flex-none px-4">
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-sm">
                        {{ languages.find((l) => l.code === currentLocale)?.flag }}
                        <span class="ml-2">{{ currentLocale.toUpperCase() }}</span>
                    </div>
                    <ul
                        tabindex="0"
                        class="dropdown-content menu bg-base-100 rounded-box z-10 mt-3 w-44 p-2 shadow"
                    >
                        <li v-for="lang in languages" :key="lang.code">
                            <button
                                type="button"
                                :class="{ active: lang.code === currentLocale }"
                                @click="switchLocale(lang.code)"
                            >
                                <span>{{ lang.flag }}</span>
                                <span>{{ lang.label }}</span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="flex-1 flex items-center justify-center p-6">
            <div class="card w-full max-w-md bg-base-100 shadow-xl">
                <div class="card-body">
                    <slot />
                </div>
            </div>
        </main>

        <footer class="footer footer-center p-4 bg-base-100 text-base-content text-sm">
            <p>{{ t('tagline') }}</p>
        </footer>
    </div>
</template>
