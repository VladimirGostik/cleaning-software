export type SortDirection = 'asc' | 'desc' | null;

export interface TableColumn<TRow extends object = Record<string, unknown>> {
    key: keyof TRow & string | string;
    label: string;
    sortable?: boolean;
    class?: string;
    headerClass?: string;
    cellClass?: string | ((row: TRow) => string | undefined);
    value?: (row: TRow) => unknown;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginator<TRow> {
    data: TRow[];
    current_page: number;
    first_page_url?: string | null;
    from: number | null;
    last_page: number;
    last_page_url?: string | null;
    links: PaginationLink[];
    next_page_url: string | null;
    path?: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}

export type FilterOperator =
    | '='
    | '!='
    | '<'
    | '<='
    | '>'
    | '>='
    | 'between'
    | '~';

export type FilterType =
    | 'string'
    | 'text'
    | 'number'
    | 'boolean'
    | 'date'
    | 'datetime'
    | 'enum'
    | 'select'
    | 'autocomplete';

export interface FilterOption {
    label: string;
    value: string;
}

export interface FilterConfig {
    property: string;
    label: string;
    type: FilterType;
    operators?: FilterOperator[];
    defaultOperator?: FilterOperator;
    options?: FilterOption[];
    multiple?: boolean;
    clearable?: boolean;
    placeholder?: string;
}

export interface FilterState extends FilterConfig {
    id: string;
    value: string | null;
    operator: FilterOperator;
}

export interface TableTabOption {
    label: string;
    value: string;
    filters?: Record<string, string>;
    clearFilters?: string[];
}

export interface TableTabsConfig {
    property: string;
    options: TableTabOption[];
    defaultTab?: string;
}
