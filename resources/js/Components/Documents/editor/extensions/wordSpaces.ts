import { Extension, type Editor } from '@tiptap/vue-3';

type Transaction = Editor['state']['tr'];

const NBSP = '\u00a0';
const RUN_RE = /[ \u00a0]{2,}/g;
const MOUSTACHE_RE = /(\{\{.*?\}\})/s;
const CARRIED_META = ['preventUpdate', 'addToHistory'] as const;

function canonicalRun(run: string): string {
    return ' \u00a0'.repeat(Math.floor(run.length / 2)) + (run.length % 2 === 1 ? ' ' : '');
}

interface RunRewrite {
    index: number;
    length: number;
    replacement: string;
}

function runRewrites(text: string): RunRewrite[] {
    const rewrites: RunRewrite[] = [];
    let offset = 0;

    for (const piece of text.split(MOUSTACHE_RE)) {
        if (!piece.startsWith('{{')) {
            for (const match of piece.matchAll(RUN_RE)) {
                const replacement = canonicalRun(match[0]);
                if (replacement !== match[0]) {
                    rewrites.push({ index: offset + match.index, length: match[0].length, replacement });
                }
            }
        }
        offset += piece.length;
    }

    return rewrites;
}

export function canonicalSpaces(text: string): string {
    return runRewrites(text).reduceRight(
        (result, { index, length, replacement }) => result.slice(0, index) + replacement + result.slice(index + length),
        text,
    );
}

export function canonicalSpacesInHtml(html: string): string {
    return html
        .split(/(<[^>]*>)/)
        .map((part) =>
            part.startsWith('<') ? part : canonicalSpaces(part.replaceAll('&nbsp;', NBSP)).replaceAll(NBSP, '&nbsp;'),
        )
        .join('');
}

function changedRanges(transaction: Transaction): { from: number; to: number }[] {
    const ranges: { from: number; to: number }[] = [];

    transaction.mapping.maps.forEach((map, index) => {
        const rest = transaction.mapping.slice(index + 1);
        map.forEach((_oldStart, _oldEnd, newStart, newEnd) => {
            ranges.push({ from: rest.map(newStart, -1), to: rest.map(newEnd, 1) });
        });
    });

    return ranges;
}

export const WordSpaces = Extension.create({
    name: 'wordSpaces',

    onTransaction({ transaction }) {
        if (!transaction.docChanged || transaction.getMeta('wordSpaces')) return;

        const { doc, tr, schema } = this.editor.state;
        const visited = new Set<number>();

        for (const range of changedRanges(transaction)) {
            const from = Math.max(0, range.from);
            const to = Math.min(doc.content.size, range.to);

            doc.nodesBetween(from, to, (node, pos) => {
                if (!node.isText || visited.has(pos)) return;
                visited.add(pos);

                const text = node.text ?? '';
                for (const { index, length, replacement } of runRewrites(text)) {
                    for (let offset = 0; offset < length; offset++) {
                        if (replacement[offset] === text[index + offset]) continue;
                        const at = pos + index + offset;
                        tr.replaceWith(at, at + 1, schema.text(replacement[offset]!, node.marks));
                    }
                }
            });
        }

        if (!tr.docChanged) return;

        tr.setMeta('wordSpaces', true);
        for (const key of CARRIED_META) {
            const value = transaction.getMeta(key);
            if (value !== undefined) tr.setMeta(key, value);
        }

        this.editor.view.dispatch(tr);
    },
});
