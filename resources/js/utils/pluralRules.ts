import type { PluralizationRule } from 'vue-i18n';

const slavicFour = (choice: number, few: (n: number) => boolean, one: (n: number) => boolean): number => {
    const n = Math.abs(choice);
    if (n === 0) return 0;
    if (one(n)) return 1;
    if (few(n)) return 2;
    return 3;
};

const skRule: PluralizationRule = (choice, choicesLength, orgRule) => {
    if (choicesLength !== 4) return orgRule ? orgRule(choice, choicesLength) : 0;
    return slavicFour(
        choice,
        (n) => n >= 2 && n <= 4,
        (n) => n === 1,
    );
};

const ukRule: PluralizationRule = (choice, choicesLength, orgRule) => {
    if (choicesLength !== 4) return orgRule ? orgRule(choice, choicesLength) : 0;
    return slavicFour(
        choice,
        (n) => n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14),
        (n) => n % 10 === 1 && n % 100 !== 11,
    );
};

export const pluralRules: Record<string, PluralizationRule> = { sk: skRule, uk: ukRule };
