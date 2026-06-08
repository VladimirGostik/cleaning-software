import { computed, type ComputedRef } from 'vue';
import type { InertiaForm } from './useFormContext';

interface FieldProps {
    field?: string;
    error?: string;
}

export function useFieldError(
    props: FieldProps,
    form: InertiaForm | undefined,
): ComputedRef<string | undefined> {
    return computed<string | undefined>(() => {
        if (props.error !== undefined) {
            return props.error || undefined;
        }
        if (!props.field || !form) {
            return undefined;
        }
        const errors = form.errors as Record<string, string | undefined>;
        return errors[props.field] ?? errors[props.field + '.0'] ?? undefined;
    });
}
