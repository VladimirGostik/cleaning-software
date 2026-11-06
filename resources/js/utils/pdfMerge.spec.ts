import { describe, expect, it } from 'vitest';
import { PDFDocument } from 'pdf-lib';
import { mergePdfs } from './pdfMerge';

async function pdfWithPages(pages: number): Promise<Blob> {
    const doc = await PDFDocument.create();
    for (let i = 0; i < pages; i++) doc.addPage();

    return new Blob([new Uint8Array(await doc.save())], { type: 'application/pdf' });
}

describe('mergePdfs', () => {
    it('sums the pages of all parts in a single PDF', async () => {
        const merged = await mergePdfs([await pdfWithPages(2), await pdfWithPages(1), await pdfWithPages(3)]);

        expect(merged.type).toBe('application/pdf');
        const result = await PDFDocument.load(await merged.arrayBuffer());
        expect(result.getPageCount()).toBe(6);
    });
});
