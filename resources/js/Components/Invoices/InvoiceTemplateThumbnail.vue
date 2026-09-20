<script setup lang="ts">
defineProps<{
    template: App.Enums.InvoiceTemplateEnum;
}>();
</script>

<template>
    <!-- Classic: supplier/customer header grid with dotted rules, items table, QR + signature row, payment strip -->
    <svg
        v-if="template === 'classic'"
        viewBox="0 0 80 100"
        preserveAspectRatio="xMidYMid meet"
        class="h-full w-full"
        aria-hidden="true"
    >
        <rect x="5" y="5" width="16" height="2.5" class="fill-base-300" />
        <rect x="5" y="10" width="26" height="3" class="fill-base-300/70" />
        <rect x="5" y="15" width="21" height="2.5" class="fill-base-300/50" />
        <rect x="5" y="19.5" width="17" height="2.5" class="fill-base-300/50" />
        <rect x="38" y="5" width="37" height="8" rx="1" class="fill-base-300/60" />
        <line x1="5" y1="26" x2="75" y2="26" class="stroke-base-300" stroke-width="0.7" stroke-dasharray="1.5 1.5" />
        <rect x="45" y="29" width="14" height="2.5" class="fill-base-300" />
        <rect x="45" y="34" width="30" height="3" class="fill-base-300/70" />
        <rect x="45" y="39" width="24" height="2.5" class="fill-base-300/50" />
        <line x1="5" y1="45" x2="75" y2="45" class="stroke-base-300" stroke-width="0.7" stroke-dasharray="1.5 1.5" />
        <rect x="5" y="48" width="28" height="2.5" class="fill-base-300/50" />
        <rect x="5" y="52.5" width="22" height="2.5" class="fill-base-300/40" />
        <rect x="49" y="48" width="26" height="2.5" class="fill-base-300/50" />
        <rect x="55" y="52.5" width="20" height="2.5" class="fill-base-300/40" />
        <line x1="5" y1="59" x2="75" y2="59" class="stroke-base-content/40" stroke-width="0.8" />
        <rect x="5" y="60.5" width="70" height="3" class="fill-base-300/60" />
        <line x1="5" y1="64.5" x2="75" y2="64.5" class="stroke-base-300" stroke-width="0.4" />
        <rect x="5" y="65.5" width="70" height="2.5" class="fill-base-300/30" />
        <rect x="5" y="69" width="70" height="2.5" class="fill-base-300/20" />
        <line x1="5" y1="72.5" x2="75" y2="72.5" class="stroke-base-300" stroke-width="0.4" />
        <rect x="5" y="75" width="26" height="2.5" class="fill-base-300/35" />
        <rect x="50" y="74.5" width="25" height="4.5" rx="0.5" class="fill-base-300/70" />
        <rect x="5" y="81" width="9" height="9" rx="0.5" fill="none" class="stroke-base-300" stroke-width="0.7" />
        <path
            d="M52 88 q4 -5 7 -1 t6 -2 t7 1"
            fill="none"
            class="stroke-base-content/35"
            stroke-width="0.8"
            stroke-linecap="round"
        />
        <rect x="5" y="92.5" width="70" height="5.5" rx="0.5" class="fill-info/20" />
        <line x1="22.5" y1="92.5" x2="22.5" y2="98" class="stroke-base-100" stroke-width="0.8" />
        <line x1="40" y1="92.5" x2="40" y2="98" class="stroke-base-100" stroke-width="0.8" />
        <line x1="57.5" y1="92.5" x2="57.5" y2="98" class="stroke-base-100" stroke-width="0.8" />
    </svg>

    <!-- Modern: full-width colored header band, left-aligned title, accent sidebar stripe -->
    <svg
        v-else-if="template === 'modern'"
        viewBox="0 0 80 100"
        preserveAspectRatio="xMidYMid meet"
        class="h-full w-full text-primary"
        aria-hidden="true"
    >
        <rect x="0" y="0" width="80" height="20" rx="0" fill="currentColor" fill-opacity="0.18" />
        <rect x="0" y="0" width="4" height="100" fill="currentColor" fill-opacity="0.7" />
        <rect x="9" y="6" width="38" height="5" rx="1" fill="currentColor" fill-opacity="0.6" />
        <rect x="9" y="13" width="24" height="3" rx="1" class="fill-base-content/20" />
        <rect x="9" y="26" width="28" height="3" rx="1" class="fill-base-content/25" />
        <rect x="9" y="31" width="20" height="3" rx="1" class="fill-base-content/18" />
        <rect x="9" y="40" width="68" height="4" rx="0.5" fill="currentColor" fill-opacity="0.22" />
        <rect x="9" y="47" width="68" height="3" rx="0.5" class="fill-base-content/12" />
        <rect x="9" y="53" width="68" height="3" rx="0.5" class="fill-base-content/8" />
        <rect x="9" y="59" width="68" height="3" rx="0.5" class="fill-base-content/12" />
        <rect x="40" y="67" width="37" height="4" rx="0.5" fill="currentColor" fill-opacity="0.3" />
    </svg>

    <!-- Minimal: lots of whitespace, thin title line, no header band, sparse rows, hairlines -->
    <svg v-else viewBox="0 0 80 100" preserveAspectRatio="xMidYMid meet" class="h-full w-full" aria-hidden="true">
        <rect x="5" y="8" width="42" height="3" rx="0.5" class="fill-base-content/35" />
        <line x1="5" y1="15" x2="75" y2="15" class="stroke-base-300/60" stroke-width="0.5" />
        <rect x="5" y="20" width="26" height="2.5" rx="0.5" class="fill-base-content/20" />
        <rect x="5" y="25" width="18" height="2.5" rx="0.5" class="fill-base-content/15" />
        <line x1="5" y1="35" x2="75" y2="35" class="stroke-base-300/60" stroke-width="0.5" />
        <rect x="5" y="39" width="68" height="2.5" rx="0.5" class="fill-base-content/12" />
        <line x1="5" y1="44" x2="75" y2="44" class="stroke-base-300/40" stroke-width="0.4" />
        <rect x="5" y="47" width="68" height="2.5" rx="0.5" class="fill-base-content/8" />
        <line x1="5" y1="52" x2="75" y2="52" class="stroke-base-300/40" stroke-width="0.4" />
        <rect x="5" y="55" width="68" height="2.5" rx="0.5" class="fill-base-content/12" />
        <line x1="5" y1="62" x2="75" y2="62" class="stroke-base-content/25" stroke-width="0.5" />
        <rect x="48" y="65" width="27" height="2.5" rx="0.5" class="fill-base-content/25" />
    </svg>
</template>
