<script setup lang="ts">
    defineProps<{
        template: App.Enums.InvoiceTemplateEnum;
    }>();
</script>

<template>
    <!-- Classic: centered title bar, two address blocks, table rows -->
    <svg
        v-if="template === 'classic'"
        viewBox="0 0 80 100"
        preserveAspectRatio="xMidYMid meet"
        class="w-full h-full"
        aria-hidden="true"
    >
        <!-- Header title bar centered -->
        <rect x="15" y="5" width="50" height="7" rx="1" class="fill-base-300" />
        <!-- Two address blocks side by side -->
        <rect x="5" y="17" width="30" height="4" rx="1" class="fill-base-300/70" />
        <rect x="5" y="23" width="22" height="3" rx="1" class="fill-base-300/50" />
        <rect x="45" y="17" width="30" height="4" rx="1" class="fill-base-300/70" />
        <rect x="45" y="23" width="22" height="3" rx="1" class="fill-base-300/50" />
        <!-- Ruled divider line -->
        <line x1="5" y1="31" x2="75" y2="31" class="stroke-base-300" stroke-width="0.8" />
        <!-- Table header row -->
        <rect x="5" y="34" width="70" height="4" rx="0.5" class="fill-base-300/60" />
        <!-- Table data rows -->
        <rect x="5" y="41" width="70" height="3" rx="0.5" class="fill-base-300/30" />
        <rect x="5" y="47" width="70" height="3" rx="0.5" class="fill-base-300/20" />
        <rect x="5" y="53" width="70" height="3" rx="0.5" class="fill-base-300/30" />
        <!-- Total line -->
        <line x1="5" y1="60" x2="75" y2="60" class="stroke-base-300" stroke-width="0.8" />
        <rect x="45" y="63" width="30" height="4" rx="0.5" class="fill-base-300/60" />
    </svg>

    <!-- Modern: full-width colored header band, left-aligned title, accent sidebar stripe -->
    <svg
        v-else-if="template === 'modern'"
        viewBox="0 0 80 100"
        preserveAspectRatio="xMidYMid meet"
        class="w-full h-full text-primary"
        aria-hidden="true"
    >
        <!-- Full-width colored header band -->
        <rect x="0" y="0" width="80" height="20" rx="0" fill="currentColor" fill-opacity="0.18" />
        <!-- Accent left sidebar stripe -->
        <rect x="0" y="0" width="4" height="100" fill="currentColor" fill-opacity="0.7" />
        <!-- Bold title left-aligned -->
        <rect x="9" y="6" width="38" height="5" rx="1" fill="currentColor" fill-opacity="0.6" />
        <!-- Subtitle line -->
        <rect x="9" y="13" width="24" height="3" rx="1" class="fill-base-content/20" />
        <!-- Address block -->
        <rect x="9" y="26" width="28" height="3" rx="1" class="fill-base-content/25" />
        <rect x="9" y="31" width="20" height="3" rx="1" class="fill-base-content/18" />
        <!-- Table header with accent -->
        <rect x="9" y="40" width="68" height="4" rx="0.5" fill="currentColor" fill-opacity="0.22" />
        <!-- Table rows -->
        <rect x="9" y="47" width="68" height="3" rx="0.5" class="fill-base-content/12" />
        <rect x="9" y="53" width="68" height="3" rx="0.5" class="fill-base-content/8" />
        <rect x="9" y="59" width="68" height="3" rx="0.5" class="fill-base-content/12" />
        <!-- Total -->
        <rect x="40" y="67" width="37" height="4" rx="0.5" fill="currentColor" fill-opacity="0.3" />
    </svg>

    <!-- Minimal: lots of whitespace, thin title line, no header band, sparse rows, hairlines -->
    <svg
        v-else
        viewBox="0 0 80 100"
        preserveAspectRatio="xMidYMid meet"
        class="w-full h-full"
        aria-hidden="true"
    >
        <!-- Thin title line only -->
        <rect x="5" y="8" width="42" height="3" rx="0.5" class="fill-base-content/35" />
        <!-- Hairline separator -->
        <line x1="5" y1="15" x2="75" y2="15" class="stroke-base-300/60" stroke-width="0.5" />
        <!-- Sparse address -->
        <rect x="5" y="20" width="26" height="2.5" rx="0.5" class="fill-base-content/20" />
        <rect x="5" y="25" width="18" height="2.5" rx="0.5" class="fill-base-content/15" />
        <!-- Hairline before table -->
        <line x1="5" y1="35" x2="75" y2="35" class="stroke-base-300/60" stroke-width="0.5" />
        <!-- Sparse table rows with hairlines only -->
        <rect x="5" y="39" width="68" height="2.5" rx="0.5" class="fill-base-content/12" />
        <line x1="5" y1="44" x2="75" y2="44" class="stroke-base-300/40" stroke-width="0.4" />
        <rect x="5" y="47" width="68" height="2.5" rx="0.5" class="fill-base-content/8" />
        <line x1="5" y1="52" x2="75" y2="52" class="stroke-base-300/40" stroke-width="0.4" />
        <rect x="5" y="55" width="68" height="2.5" rx="0.5" class="fill-base-content/12" />
        <!-- Hairline before total -->
        <line x1="5" y1="62" x2="75" y2="62" class="stroke-base-content/25" stroke-width="0.5" />
        <!-- Total right-aligned, sparse -->
        <rect x="48" y="65" width="27" height="2.5" rx="0.5" class="fill-base-content/25" />
    </svg>
</template>
