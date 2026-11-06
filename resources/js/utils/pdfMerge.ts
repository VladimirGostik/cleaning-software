export async function mergePdfs(parts: Blob[]): Promise<Blob> {
    const { PDFDocument } = await import('pdf-lib');
    const merged = await PDFDocument.create();

    for (const part of parts) {
        const source = await PDFDocument.load(await part.arrayBuffer());
        const pages = await merged.copyPages(source, source.getPageIndices());
        pages.forEach((page) => merged.addPage(page));
    }

    const bytes = await merged.save();

    return new Blob([new Uint8Array(bytes)], { type: 'application/pdf' });
}

export function downloadBlob(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 10_000);
}
