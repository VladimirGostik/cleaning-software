export function countryName(code: string, i18n: { t: (key: string) => string; te: (key: string) => boolean }): string {
    const key = `country_${code.toLowerCase()}`;

    return i18n.te(key) ? i18n.t(key) : code;
}
