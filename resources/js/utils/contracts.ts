import { formatDate, formatDatetime, toDateInputValue } from '@/utils/date';
import { CONTRACT_FILE_CATEGORIES, CONTRACT_FILE_CONTRACTABLE_TYPES } from '@/utils/enums';

type Prefill = App.Data.Contracts.ContractCreatePrefillData;
type Contract = App.Data.Contracts.ContractDetailData;

export interface ContractFileInitialData {
    title: string;
    number: string | null;
    category: App.Enums.ContractCategoryEnum;
    term_type: App.Enums.ContractTermTypeEnum;
    contractable_type: App.Enums.ContractableTypeEnum;
    contractable_id: string;
    valid_from: string;
    end_date: string | null;
    is_signed: boolean;
    signed_on: string | null;
    notes: string | null;
    file_uuid: string | null;
}

export function contractCancelHref(
    contract: Pick<Contract, 'id'> | null | undefined,
    prefill?: Prefill | null,
): string {
    if (contract) return `/contracts/${contract.id}`;

    const id = prefill?.contractable_id;
    if (!id) return '/contracts';

    switch (prefill?.contractable_type) {
        case 'client':
            return `/clients/${id}`;
        case 'cleaning_object':
            return `/objects/${id}`;
        case 'tenant_membership':
            return `/employees/${id}`;
        default:
            return '/contracts';
    }
}

export function fileFlowAvailable(prefill: Pick<Prefill, 'contractable_type'> | null | undefined): boolean {
    return prefill?.contractable_type !== 'tenant_membership';
}

function narrowCategory(category: App.Enums.ContractCategoryEnum | null): App.Enums.ContractCategoryEnum {
    return category !== null && CONTRACT_FILE_CATEGORIES.includes(category) ? category : CONTRACT_FILE_CATEGORIES[0];
}

function narrowContractableType(type: App.Enums.ContractableTypeEnum | null): App.Enums.ContractableTypeEnum {
    return type !== null && CONTRACT_FILE_CONTRACTABLE_TYPES.includes(type)
        ? type
        : CONTRACT_FILE_CONTRACTABLE_TYPES[0];
}

export function initialFileFormData(source: Prefill | Contract | null): ContractFileInitialData {
    if (source !== null && 'id' in source) {
        return {
            title: source.title,
            number: source.number,
            category: narrowCategory(source.category),
            term_type: source.term_type,
            contractable_type: narrowContractableType(source.contractable_type),
            contractable_id: source.contractable_id,
            valid_from: toDateInputValue(source.valid_from),
            end_date: toDateInputValue(source.end_date) || null,
            is_signed: source.is_signed,
            signed_on: source.is_signed && source.signed_at ? source.signed_at.slice(0, 10) : null,
            notes: source.notes,
            file_uuid: null,
        };
    }

    return {
        title: '',
        number: null,
        category: narrowCategory(source?.category ?? null),
        term_type: 'indefinite',
        contractable_type: narrowContractableType(source?.contractable_type ?? null),
        contractable_id: source?.contractable_id ?? '',
        valid_from: toDateInputValue(new Date()),
        end_date: null,
        is_signed: true,
        signed_on: null,
        notes: null,
        file_uuid: null,
    };
}

export function contractSignatureLabel(
    t: (key: string) => string,
    contract: Pick<Contract, 'source' | 'is_signed' | 'signed_at'>,
): string {
    if (contract.source === 'file') {
        if (!contract.is_signed) return t('contract_unsigned_draft');

        return contract.signed_at ? formatDate(contract.signed_at.slice(0, 10)) : t('contract_signed_date_unknown');
    }

    return contract.signed_at ? formatDatetime(contract.signed_at) : t('contract_not_signed');
}

export function fileAccept(constraints: { allowed_mimes: string[]; allowed_extensions?: string[] }): string {
    const extensions = (constraints.allowed_extensions ?? []).map((extension) => `.${extension.replace(/^\./, '')}`);

    return [...new Set([...constraints.allowed_mimes, ...extensions])].join(',');
}
