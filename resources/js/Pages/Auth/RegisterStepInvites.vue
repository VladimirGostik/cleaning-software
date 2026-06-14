<script setup lang="ts">
    import { computed } from 'vue';
    import type { InertiaForm } from '@inertiajs/vue3';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { RegisterFormData } from '@/Pages/Auth/Register.vue';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';

    const props = defineProps<{
        form: InertiaForm<RegisterFormData>;
        roles: string[];
    }>();

    const emit = defineEmits<{
        (e: 'back'): void;
        (e: 'submit'): void;
    }>();

    const { t } = useTranslate();

    const roleOptions = computed<SelectOption[]>(() =>
        props.roles.map((r) => ({ value: r, label: r })),
    );
</script>

<template>
    <div class="flex flex-col gap-[18px]">
        <div class="mb-2">
            <h2 class="text-[22px] font-bold text-slate-900">{{ t('register.step3_title') }}</h2>
        </div>

        <!-- Invite rows -->
        <div
            v-for="(invite, index) in form.invites"
            :key="index"
            class="flex flex-col gap-1.5"
        >
            <div class="grid grid-cols-[1fr_auto] gap-2">
                <input
                    :id="`reg-invite-email-${index}`"
                    v-model="invite.email"
                    type="email"
                    :placeholder="t('register.invite_email')"
                    class="w-full rounded-lg border border-slate-200 bg-white py-2.5 px-3 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                    :class="{
                        'border-red-400 focus:border-red-400 focus:ring-red-100':
                            form.errors[`invites.${index}.email`],
                    }"
                    :aria-label="`${t('register.invite_email')} ${index + 1}`"
                />
                <SelectInput
                    v-model="invite.role_name"
                    :options="roleOptions"
                    :placeholder="t('register.invite_role')"
                    :error="(form.errors as Record<string, string>)[`invites.${index}.role_name`]"
                    :label="`${t('register.invite_role')} ${index + 1}`"
                />
            </div>
            <p v-if="form.errors[`invites.${index}.email`]" class="text-xs text-red-500">
                {{ form.errors[`invites.${index}.email`] }}
            </p>
        </div>

        <!-- Navigation -->
        <div class="flex gap-3 pt-1">
            <button
                type="button"
                class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                @click="emit('back')"
            >
                {{ t('register.back') }}
            </button>
            <button
                type="button"
                class="flex-1 rounded-lg border border-slate-200 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                @click="emit('submit')"
            >
                {{ t('register.skip') }}
            </button>
            <button
                type="button"
                class="flex-1 flex items-center justify-center rounded-lg py-2.5 text-sm font-semibold text-white transition auth-submit-btn disabled:opacity-70"
                :disabled="form.processing"
                @click="emit('submit')"
            >
                <span
                    v-if="form.processing"
                    class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
                />
                {{ t('register.submit') }}
            </button>
        </div>
    </div>
</template>
