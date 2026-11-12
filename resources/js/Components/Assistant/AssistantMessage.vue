<script setup lang="ts">
import type { DeepReadonly } from 'vue';
import type { ChatMessage } from '@/Composables/useAssistantChat';
import AssistantAttachmentChip from '@/Components/Assistant/AssistantAttachmentChip.vue';
import AssistantActionLinks from '@/Components/Assistant/AssistantActionLinks.vue';
import AssistantPendingAction from '@/Components/Assistant/AssistantPendingAction.vue';
import { assistantAttachmentKind } from '@/utils/assistant';

defineProps<{ message: DeepReadonly<ChatMessage> }>();
</script>

<template>
    <div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
        <div
            class="max-w-[85%] rounded-card-nested px-3 py-2 text-sm"
            :class="message.role === 'user' ? 'bg-primary-soft' : 'bg-base-200'"
        >
            <ul v-if="message.attachmentNames.length > 0" class="mb-1 flex flex-wrap gap-1.5">
                <li v-for="(name, index) in message.attachmentNames" :key="index" class="max-w-full">
                    <AssistantAttachmentChip
                        :name="name"
                        :kind="assistantAttachmentKind({ name, type: '' }) ?? 'image'"
                    />
                </li>
            </ul>
            <p v-if="message.content !== ''" class="whitespace-pre-wrap break-words">{{ message.content }}</p>

            <div v-if="message.securityNotice" class="alert alert-warning mt-2 text-xs" role="alert">
                {{ message.securityNotice }}
            </div>

            <p v-if="message.attachmentNote" class="mt-2 text-xs text-muted" role="status">
                {{ message.attachmentNote }}
            </p>

            <AssistantActionLinks :actions="message.actions" />

            <AssistantPendingAction
                v-if="message.pending"
                :action="message.pending"
                :outcome="message.pendingOutcome"
            />
        </div>
    </div>
</template>
