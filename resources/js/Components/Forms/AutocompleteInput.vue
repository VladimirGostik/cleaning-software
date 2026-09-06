<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/Composables/useToast';

export type AutocompleteOption = Record<string, unknown>;

interface Props {
    modelValue: string | null;
    url: string;
    placeholder?: string;
    minChars?: number;
    debounceMs?: number;
    labelKey?: string;
    valueKey?: string;
    secondaryKey?: string;
    disabled?: boolean;
    error?: string | null;
    initialOption?: AutocompleteOption | null;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: '',
    minChars: 2,
    debounceMs: 300,
    labelKey: 'name',
    valueKey: 'id',
    secondaryKey: undefined,
    disabled: false,
    error: null,
    initialOption: null,
});

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const { t } = useI18n();
const toast = useToast();

const inputEl = ref<HTMLInputElement | null>(null);
const query = ref('');
const results = ref<AutocompleteOption[]>([]);
const loading = ref(false);
const open = ref(false);
const highlightedIndex = ref(0);
const selectedLabel = ref<string | null>(null);

let abortController: AbortController | null = null;
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let blurTimer: ReturnType<typeof setTimeout> | null = null;

const displayValue = computed(() => {
    if (open.value) return query.value;
    return selectedLabel.value ?? query.value;
});

const showClear = computed(() => props.modelValue !== null && !props.disabled);

const showHint = computed(() => {
    if (loading.value) return false;
    const len = query.value.trim().length;
    return len > 0 && len < props.minChars && results.value.length === 0;
});

const showNoResults = computed(() => {
    if (loading.value) return false;
    const len = query.value.trim().length;
    return results.value.length === 0 && (len === 0 || len >= props.minChars);
});

function syncInitialLabel() {
    if (props.modelValue && props.initialOption && props.initialOption[props.valueKey] === props.modelValue) {
        const label = props.initialOption[props.labelKey];
        if (typeof label === 'string') {
            selectedLabel.value = label;
            return;
        }
    }
    if (!props.modelValue) {
        selectedLabel.value = null;
    }
}

watch(() => [props.modelValue, props.initialOption], syncInitialLabel, { immediate: true });

function cancelInFlight() {
    if (abortController) {
        abortController.abort();
        abortController = null;
    }
    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }
}

async function runSearch(rawQuery: string) {
    const trimmed = rawQuery.trim();

    if (trimmed.length > 0 && trimmed.length < props.minChars) {
        results.value = [];
        loading.value = false;
        return;
    }

    if (abortController) {
        abortController.abort();
    }

    abortController = new AbortController();
    const controller = abortController;
    loading.value = true;

    try {
        const response = await fetch(`${props.url}?q=${encodeURIComponent(trimmed)}`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const payload = (await response.json()) as AutocompleteOption[];

        if (controller.signal.aborted || trimmed !== query.value.trim()) {
            return;
        }

        results.value = payload;
        highlightedIndex.value = 0;
        loading.value = false;
    } catch (err) {
        if (err instanceof DOMException && err.name === 'AbortError') {
            return;
        }
        loading.value = false;
        results.value = [];
        toast.error(t('autocomplete_failed'));
    }
}

function onInput(event: Event) {
    const target = event.target as HTMLInputElement;
    query.value = target.value;
    open.value = true;

    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
    }

    const len = target.value.trim().length;

    if (len === 0) {
        cancelInFlight();
        void runSearch('');
        return;
    }

    if (len < props.minChars) {
        cancelInFlight();
        results.value = [];
        loading.value = false;
        return;
    }

    debounceTimer = setTimeout(() => {
        void runSearch(query.value);
    }, props.debounceMs);
}

function onFocus() {
    if (blurTimer !== null) {
        clearTimeout(blurTimer);
        blurTimer = null;
    }
    open.value = true;
    if (selectedLabel.value) {
        query.value = '';
    }
    if (query.value.trim().length === 0) {
        void runSearch('');
    }
}

function onBlur() {
    if (blurTimer !== null) {
        clearTimeout(blurTimer);
    }
    blurTimer = setTimeout(() => {
        open.value = false;
    }, 150);
}

function selectOption(option: AutocompleteOption) {
    const value = option[props.valueKey];
    const label = option[props.labelKey];

    if (typeof value !== 'string') return;

    emit('update:modelValue', value);
    selectedLabel.value = typeof label === 'string' ? label : '';
    query.value = '';
    results.value = [];
    open.value = false;
    cancelInFlight();
    inputEl.value?.blur();
}

function clearSelection() {
    cancelInFlight();
    emit('update:modelValue', null);
    selectedLabel.value = null;
    query.value = '';
    results.value = [];
    open.value = false;
    inputEl.value?.focus();
}

function moveHighlight(delta: number) {
    if (results.value.length === 0) return;
    open.value = true;
    const next = highlightedIndex.value + delta;
    highlightedIndex.value = Math.max(0, Math.min(results.value.length - 1, next));
}

function onKeydown(event: KeyboardEvent) {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            moveHighlight(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            moveHighlight(-1);
            break;
        case 'Enter':
            if (open.value && results.value.length > 0) {
                event.preventDefault();
                selectOption(results.value[highlightedIndex.value]);
            }
            break;
        case 'Escape':
            event.preventDefault();
            open.value = false;
            inputEl.value?.blur();
            break;
        case 'Tab':
            open.value = false;
            break;
    }
}

onBeforeUnmount(() => {
    cancelInFlight();
    if (blurTimer !== null) clearTimeout(blurTimer);
});
</script>

<template>
    <div class="relative w-full">
        <div class="relative w-full">
            <input
                ref="inputEl"
                type="text"
                class="input w-full pr-10"
                :class="{ 'input-error': error }"
                :value="displayValue"
                :placeholder="placeholder"
                :disabled="disabled"
                autocomplete="off"
                @input="onInput"
                @focus="onFocus"
                @blur="onBlur"
                @keydown="onKeydown"
            />
            <button
                v-if="showClear"
                type="button"
                class="absolute inset-y-0 right-0 px-3 text-base-content/60 hover:text-base-content"
                tabindex="-1"
                :aria-label="t('clear_filters')"
                @mousedown.prevent="clearSelection"
            >
                ×
            </button>
        </div>

        <ul
            v-if="open"
            class="menu bg-base-100 rounded-box shadow z-30 w-full max-h-64 overflow-y-auto absolute top-full left-0 mt-1 border border-base-200"
        >
            <li v-if="loading">
                <span class="text-base-content/60">
                    <span class="loading loading-spinner loading-xs" />
                    {{ t('searching') }}
                </span>
            </li>
            <li v-else-if="showHint">
                <span class="text-base-content/50">
                    {{ t('type_to_search', { n: minChars }) }}
                </span>
            </li>
            <li v-else-if="showNoResults">
                <span class="text-base-content/50">{{ t('no_results') }}</span>
            </li>
            <li
                v-for="(option, i) in results"
                :key="String(option[valueKey])"
                :class="{ 'bg-base-200': i === highlightedIndex }"
            >
                <button
                    type="button"
                    class="flex flex-col items-start gap-0"
                    @mousedown.prevent="selectOption(option)"
                    @mouseenter="highlightedIndex = i"
                >
                    <span>{{ option[labelKey] }}</span>
                    <span v-if="secondaryKey && option[secondaryKey]" class="text-xs text-base-content/60">
                        {{ option[secondaryKey] }}
                    </span>
                </button>
            </li>
        </ul>

        <p v-if="error" class="text-error text-sm mt-1">{{ error }}</p>
    </div>
</template>
