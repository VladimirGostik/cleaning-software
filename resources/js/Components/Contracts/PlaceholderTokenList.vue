<script setup lang="ts">
    import { useTranslate } from '@/Composables/useTranslate';

    withDefaults(
        defineProps<{
            tokens: { token: string; label: string }[];
            title?: string;
        }>(),
        {
            title: '',
        },
    );

    const emit = defineEmits<{
        insert: [token: string];
    }>();

    const { t } = useTranslate();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body p-4">
            <h3 class="font-semibold text-sm mb-2">
                {{ title || t('contracts.tokens.title') }}
            </h3>
            <div class="flex flex-col gap-0.5">
                <button
                    v-for="item in tokens"
                    :key="item.token"
                    type="button"
                    class="btn btn-ghost btn-xs w-full justify-start font-mono text-left"
                    :aria-label="t('contracts.tokens.insert_aria').replace('{token}', item.token)"
                    @click="emit('insert', item.token)"
                >
                    <span class="truncate">{{ item.label }}</span>
                    <span class="text-base-content/40 ml-auto font-mono text-xs shrink-0">
                        {{ item.token }}
                    </span>
                </button>
            </div>
            <p v-if="tokens.length === 0" class="text-base-content/50 text-xs">
                {{ t('contracts.tokens.empty') }}
            </p>
        </div>
    </div>
</template>
