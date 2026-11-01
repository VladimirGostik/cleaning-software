const VOID_TAGS = new Set(['br', 'hr']);

function escapeText(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function escapeAttribute(value: string): string {
    return escapeText(value).replace(/"/g, '&quot;');
}

// DOM style serialisation (ProseMirror sets style.cssText) turns hex into rgb(); both are valid for the BE.
function hexToRgb(value: string): string {
    const match = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(value);
    if (!match) return value;
    const hex = match[1]!.length === 3 ? [...match[1]!].map((c) => c + c).join('') : match[1]!;
    const [r, g, b] = [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16));

    return `rgb(${r}, ${g}, ${b})`;
}

function canonicalStyle(tag: string, style: string): string {
    return style
        .split(';')
        .map((declaration) => declaration.trim())
        .filter(Boolean)
        .map((declaration) => {
            const colon = declaration.indexOf(':');
            const property = declaration.slice(0, colon).trim();
            let value = declaration.slice(colon + 1).trim();
            if (property === 'font-family') value = value.replace(/['"]+/g, '');
            if (property === 'color' || property === 'background-color') value = hexToRgb(value);

            return { property, value };
        })
        .filter(({ property }) => !(tag === 'table' && property === 'min-width'))
        .map(({ property, value }) => `${property}: ${value};`)
        .join(' ');
}

function serialize(node: Node): string {
    if (node.nodeType === 3) return escapeText(node.textContent ?? '');
    if (node.nodeType !== 1) return '';

    const element = node as Element;
    const tag = element.tagName.toLowerCase();
    if (tag === 'colgroup' || tag === 'col') return '';

    const attributes = Array.from(element.attributes)
        .sort((a, b) => a.name.localeCompare(b.name))
        .map((attribute) => {
            const value = attribute.name === 'style' ? canonicalStyle(tag, attribute.value) : attribute.value;
            if (attribute.name === 'style' && value === '') return '';
            if ((attribute.name === 'colspan' || attribute.name === 'rowspan') && value === '1') return '';
            if (attribute.name.startsWith('data-') && value === '') return ` ${attribute.name}`;

            return ` ${attribute.name}="${escapeAttribute(value)}"`;
        })
        .join('');

    if (VOID_TAGS.has(tag)) return `<${tag}${attributes} />`;

    return `<${tag}${attributes}>${Array.from(element.childNodes).map(serialize).join('')}</${tag}>`;
}

export function canonicalDocumentHtml(html: string): string {
    const template = document.createElement('template');
    template.innerHTML = html;

    return Array.from(template.content.childNodes).map(serialize).join('');
}
