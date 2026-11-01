type DocumentTokenDef = App.Data.DocumentTemplates.DocumentTokenData;
type DocumentTokenKind = App.Enums.DocumentTokenKindEnum;

const SEGMENT = '[a-z][a-z0-9_]*';
const KEY = `${SEGMENT}(?:\\.${SEGMENT})*`;
const BLOCK_P_RE = new RegExp(
    `<p(\\s[^>]*)?>\\s*\\{\\{\\s*(block\\.${SEGMENT}(?:\\.${SEGMENT})*)\\s*\\}\\}\\s*</p>`,
    'g',
);
const TOKEN_RE = new RegExp(`\\{\\{\\s*(${KEY})\\s*\\}\\}`, 'g');
const CHIP_RE = /<span\b[^>]*\bdata-template-token="([a-z0-9_.]+)"[^>]*>[\s\S]*?<\/span>/g;
const BLOCK_RE = /<div\b([^>]*)\bdata-template-block="([a-z0-9_.]+)"([^>]*)>[\s\S]*?<\/div>/g;
const STYLE_ATTR_RE = /\bstyle="([^"]*)"/;
const BLOCK_STYLE_PROPERTIES = ['text-align', 'font-family', 'font-size'] as const;

export const TOKEN_CHIP_TONE: Record<DocumentTokenKind, string> = {
    text: 'kg-chip kg-chip--planned',
    optional: 'kg-chip kg-chip--neutral',
    flag: 'kg-chip kg-chip--warning',
    block: 'document-block-chip',
};

export function escapeHtml(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function kindOf(key: string, defs: Record<string, DocumentTokenDef>): DocumentTokenKind {
    const known = defs[key]?.kind;
    if (known) {
        return known;
    }
    if (key.startsWith('block.')) {
        return 'block';
    }
    return key.startsWith('if.') ? 'flag' : 'text';
}

function labelOf(key: string, defs: Record<string, DocumentTokenDef>): string {
    return defs[key]?.label ?? key;
}

function blockStyle(style: string): string {
    const declared = new Map<string, string>();
    for (const declaration of style.replaceAll('&quot;', '"').split(';')) {
        const colon = declaration.indexOf(':');
        if (colon < 1) continue;
        const property = declaration.slice(0, colon).trim().toLowerCase();
        const value = declaration
            .slice(colon + 1)
            .replace(/['"]+/g, '')
            .trim();
        if (value !== '') declared.set(property, value);
    }

    if (declared.get('text-align') === 'left') declared.delete('text-align');

    return BLOCK_STYLE_PROPERTIES.filter((property) => declared.has(property))
        .map((property) => `${property}: ${declared.get(property)};`)
        .join(' ');
}

function mapTextSegments(html: string, replace: (text: string) => string): string {
    return html
        .split(/(<[^>]*>)/)
        .map((part) => (part.startsWith('<') ? part : replace(part)))
        .join('');
}

export function storedToEditorHtml(html: string, defs: Record<string, DocumentTokenDef>): string {
    const withBlocks = html.replace(BLOCK_P_RE, (_m, attributes: string | undefined, key: string) => {
        const style = blockStyle(STYLE_ATTR_RE.exec(attributes ?? '')?.[1] ?? '');
        const styleAttribute = style === '' ? '' : ` style="${style}"`;

        return `<div data-template-block="${key}" data-label="${escapeHtml(labelOf(key, defs))}"${styleAttribute}></div>`;
    });

    return mapTextSegments(withBlocks, (text) =>
        text.replace(TOKEN_RE, (_m, key: string) => {
            const kind = kindOf(key, defs);
            return `<span data-template-token="${key}" data-kind="${kind}" data-label="${escapeHtml(labelOf(key, defs))}"></span>`;
        }),
    );
}

export function editorToStoredHtml(html: string): string {
    return html
        .replace(BLOCK_RE, (_m, before: string, key: string, after: string) => {
            const style = blockStyle(STYLE_ATTR_RE.exec(`${before} ${after}`)?.[1] ?? '');
            const styleAttribute = style === '' ? '' : ` style="${style}"`;

            return `<p${styleAttribute}>{{${key}}}</p>`;
        })
        .replace(CHIP_RE, (_m, key: string) => `{{${key}}}`);
}

export function storedToReadonlyHtml(html: string, tokens: readonly DocumentTokenDef[]): string {
    const defs = Object.fromEntries(tokens.map((token) => [token.key, token]));

    return mapTextSegments(html, (text) =>
        text.replace(TOKEN_RE, (_m, key: string) => {
            const kind = kindOf(key, defs);

            return `<span class="${TOKEN_CHIP_TONE[kind]}" data-template-token="${key}">${escapeHtml(labelOf(key, defs))}</span>`;
        }),
    );
}

export function usedTokenKeys(stored: string): string[] {
    const keys = new Set<string>();
    for (const match of stored.matchAll(TOKEN_RE)) {
        keys.add(match[1]);
    }

    return [...keys];
}
