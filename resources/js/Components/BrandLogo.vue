<script setup lang="ts">
import { computed } from 'vue';
import logo from '../../images/brand/kegos-logo.svg';
import logoWhite from '../../images/brand/kegos-logo-white.svg';
import logoOnOrange from '../../images/brand/kegos-logo-on-orange.svg';
import logoMono from '../../images/brand/kegos-logo-mono.svg';
import markLight from '../../images/brand/kegos-mark.svg';
import markSmall from '../../images/brand/kegos-mark-small.svg';
import markWhite from '../../images/brand/kegos-mark-white.svg';
import markOnOrange from '../../images/brand/kegos-mark-on-orange.svg';
import markMono from '../../images/brand/kegos-mark-mono.svg';

type Variant = 'light' | 'white' | 'on-orange' | 'mono';

const props = withDefaults(defineProps<{ variant?: Variant; mark?: boolean; size?: number }>(), {
    variant: 'light',
    size: 28,
});

const LOGOS: Record<Variant, string> = { light: logo, white: logoWhite, 'on-orange': logoOnOrange, mono: logoMono };
const MARKS: Record<Variant, string> = {
    light: markLight,
    white: markWhite,
    'on-orange': markOnOrange,
    mono: markMono,
};

const width = computed<number>(() => Math.round((props.size * (props.mark ? 84 : 342.4)) / 78));

const src = computed<string>(() => {
    if (!props.mark) return LOGOS[props.variant];
    return props.variant === 'light' && props.size <= 24 ? markSmall : MARKS[props.variant];
});
</script>

<template>
    <img :src="src" :width="width" :height="size" :style="{ height: `${size}px` }" class="w-auto" alt="KEGOS" />
</template>
