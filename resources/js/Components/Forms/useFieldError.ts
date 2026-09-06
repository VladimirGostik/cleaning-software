import { computed, type ComputedRef } from 'vue';
import type { useForm } from '@inertiajs/vue3';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyForm = ReturnType<typeof useForm<any>>;

interface FieldErrorOptions {
    field?: string;
    error?: string | null;
}

/**
 * Resolves a validation error message for a form field.
 *
 * Field-mode  (field + FormProvider): reads from form.errors[field].
 *             Also checks field.0 for array-field errors (e.g. "roles.0").
 * Prop-mode   (no field / no FormProvider): falls back to the :error prop.
 *
 * Dev-time warning is emitted when field is set but FormProvider is missing.
 */
export function useFieldError(props: FieldErrorOptions, form: AnyForm | null): ComputedRef<string | null> {
    if (import.meta.env.DEV && props.field && !form) {
        console.warn(
            `[FormField] field="${props.field}" is set but no <FormProvider> was found in the component tree. Validation errors will not display automatically.`,
        );
    }

    return computed(() => {
        if (props.field && form) {
            const errors = form.errors as Record<string, string | undefined>;
            return errors[props.field] ?? errors[`${props.field}.0`] ?? null;
        }

        return props.error ?? null;
    });
}

/**
 * Calls form.validate(field) only when the form supports it (precognition forms).
 * Safe to call on plain Inertia useForm instances — it's a no-op there.
 */
export function callValidate(form: AnyForm | null, field: string | undefined): void {
    if (!form || !field) {
        return;
    }

    const formWithValidate = form as { validate?: (field: string) => void };

    if (typeof formWithValidate.validate === 'function') {
        formWithValidate.validate(field);
    }
}
