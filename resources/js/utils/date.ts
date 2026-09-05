import { format, parseISO } from 'date-fns'

const DATETIME_FORMAT = 'd.M.y HH:mm:ss'
const DATE_FORMAT = 'd.M.y'

export function formatDatetime(value: string | Date | null | undefined): string {
    if (!value) return '—'
    const date = typeof value === 'string' ? parseISO(value) : value
    return format(date, DATETIME_FORMAT)
}

export function formatDate(value: string | Date | null | undefined): string {
    if (!value) return '—'
    const date = typeof value === 'string' ? parseISO(value) : value
    return format(date, DATE_FORMAT)
}

export function toDateInputValue(value: string | Date | null | undefined): string {
    if (!value) return ''
    const date = typeof value === 'string' ? parseISO(value) : value
    return format(date, 'yyyy-MM-dd')
}

export function toDatetimeInputValue(value: string | Date | null | undefined): string {
    if (!value) return ''
    const date = typeof value === 'string' ? parseISO(value) : value
    return format(date, "yyyy-MM-dd'T'HH:mm")
}
