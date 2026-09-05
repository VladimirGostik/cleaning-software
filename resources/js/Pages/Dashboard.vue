<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';

const page = usePage();

const userName = computed(() => page.props.auth.user?.name ?? '');
</script>

<template>
    <AppLayout>
        <Header :title="$t('dashboard')" />

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <p class="text-lg">
                    {{ $t('welcome') }}, {{ userName }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>
