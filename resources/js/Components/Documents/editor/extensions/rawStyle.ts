function rawStyleValue(element: HTMLElement, property: string): string | null {
    for (const declaration of (element.getAttribute('style') ?? '').split(';')) {
        const colon = declaration.indexOf(':');
        if (colon > 0 && declaration.slice(0, colon).trim().toLowerCase() === property) {
            return declaration.slice(colon + 1).trim() || null;
        }
    }

    return null;
}

// Raw style text keeps hex colours as authored; element.style would normalise them to rgb().
export function rawStyleAttribute(attribute: string, property: string, stripQuotes = false) {
    return {
        default: null,
        parseHTML: (element: HTMLElement) => {
            const value = rawStyleValue(element, property);

            return value && stripQuotes ? value.replace(/['"]+/g, '') : value;
        },
        renderHTML: (attributes: Record<string, string | null>) =>
            attributes[attribute] ? { style: `${property}: ${attributes[attribute]}` } : {},
    };
}
