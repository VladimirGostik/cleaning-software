<script setup lang="ts">
import type { DeepReadonly } from 'vue';
import type { ChatMessage } from '@/Composables/useAssistantChat';
import AssistantActionLinks from '@/Components/Assistant/AssistantActionLinks.vue';
import AssistantPendingAction from '@/Components/Assistant/AssistantPendingAction.vue';

defineProps<{ message: DeepReadonly<ChatMessage> }>();
</script>

<template>
    <div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
        <div
            class="max-w-[85%] rounded-box px-3 py-2 text-sm"
            :class="message.role === 'user' ? 'bg-primary-soft' : 'bg-base-200'"
        >
            <p class="whitespace-pre-wrap break-words">{{ message.content }}</p>

            <div v-if="message.securityNotice" class="alert alert-warning mt-2 text-xs" role="alert">
                {{ message.securityNotice }}
            </div>

            <AssistantActionLinks :actions="message.actions" />

            <AssistantPendingAction
                v-if="message.pending"
                :action="message.pending"
                :outcome="message.pendingOutcome"
            />
        </div>
    </div>
</template>
