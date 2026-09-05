export function formatBytes(bytes: number | null | undefined, decimals = 1): string {
    if (bytes == null || bytes < 0) return '—';
    if (bytes === 0) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);

    if (i === 0) return `${bytes} B`;

    return `${(bytes / Math.pow(1024, i)).toFixed(decimals)} ${units[i]}`;
}
