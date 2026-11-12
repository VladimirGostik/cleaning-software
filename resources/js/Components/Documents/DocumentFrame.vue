<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BrandLogo from '@/Components/BrandLogo.vue';
import DocumentFrameLabel from '@/Components/Documents/DocumentFrameLabel.vue';

const props = withDefaults(
    defineProps<{
        part: 'header' | 'footer';
        letterhead?: App.Data.Documents.DocumentLetterheadData | null;
        footer?: string[];
        variant?: 'letterhead' | 'kegos';
        reference?: string | null;
        labelled?: boolean;
        outlined?: boolean;
        settingsUrl?: string | null;
    }>(),
    {
        letterhead: null,
        footer: () => [],
        variant: 'letterhead',
        reference: null,
        labelled: false,
        outlined: false,
        settingsUrl: null,
    },
);

const { t } = useI18n();

const frameClass = computed(() => ({
    'document-frame--kegos': props.variant === 'kegos',
    'document-frame--outlined': props.outlined,
    'document-frame--muted': props.labelled,
}));
</script>

<template>
    <header
        v-if="part === 'header' && letterhead && variant === 'kegos'"
        class="document-kegos-header document-frame"
        :class="frameClass"
        :aria-label="t('document_letterhead_label')"
    >
        <BrandLogo :size="18" />
        <div class="document-kegos-doctype">
            <p>{{ t('quote_pdf_title') }}</p>
            <p v-if="reference" class="document-kegos-reference">{{ reference }}</p>
        </div>
    </header>

    <header
        v-else-if="part === 'header' && letterhead"
        class="document-letterhead document-frame"
        :class="frameClass"
        :aria-label="t('document_letterhead_label')"
    >
        <p>
            <strong>{{ letterhead.name }}</strong>
            <template v-if="letterhead.address">
                <br />
                {{ letterhead.address }}
            </template>
            <template v-if="letterhead.ico">
                <br />
                {{ t('client_ico') }}: {{ letterhead.ico }}
            </template>
        </p>
        <DocumentFrameLabel v-if="labelled" :settings-url="settingsUrl" />
    </header>

    <footer
        v-else-if="part === 'footer' && footer.length > 0 && variant === 'kegos'"
        class="document-kegos-footer document-frame"
        :class="frameClass"
        :aria-label="t('document_footer_label')"
    >
        <div class="document-kegos-footer-row">
            <span>{{ footer[0] }}</span>
            <span v-if="footer[1]" class="document-kegos-footer-right">{{ footer[1] }}</span>
        </div>
        <DocumentFrameLabel v-if="labelled" :settings-url="settingsUrl" />
    </footer>

    <footer
        v-else-if="part === 'footer' && footer.length > 0"
        class="document-sheet-footer document-frame"
        :class="frameClass"
        :aria-label="t('document_footer_label')"
    >
        <p v-for="line in footer" :key="line">{{ line }}</p>
        <DocumentFrameLabel v-if="labelled" :settings-url="settingsUrl" />
    </footer>
</template>
