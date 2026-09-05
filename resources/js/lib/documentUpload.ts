/**
 * Mirrors config/documents.php — keep in sync. BE (`config/documents.php` +
 * `QuoteDocumentUploadData::rules()`) is the sole enforcement authority; this
 * module is client-side pre-validation only (instant feedback, no round-trip).
 */
export const DOCUMENT_ALLOWED_MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'] as const;

export const DOCUMENT_MAX_SIZE_KB = 10240;

/**
 * Formats a byte count as a human-readable size ("2,4 MB" / "480 kB").
 */
export function formatFileSize(bytes: number): string {
    const mb = bytes / (1024 * 1024);

    if (mb >= 1) {
        return `${new Intl.NumberFormat('sk-SK', { maximumFractionDigits: 1 }).format(mb)} MB`;
    }

    const kb = bytes / 1024;

    return `${new Intl.NumberFormat('sk-SK', { maximumFractionDigits: 0 }).format(kb)} kB`;
}
