import { watch } from 'vue';
import { callValidate } from '@/Components/Forms/useFieldError';
import type { useForm } from '@inertiajs/vue3';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyForm = ReturnType<typeof useForm<any>>;

/**
 * Maps a field to the fields whose validation rules reference it.
 * `{ issue_date: ['due_date'] }` reads as "due_date's rules mention issue_date".
 */
export type DependentFields = Record<string, readonly string[]>;

/**
 * Precognition validates only the field that changed, so a rule that lives on field B
 * but points at field A (`due_date` => `after_or_equal:issue_date`) never re-runs when A
 * changes — a stale error stays on screen, and a newly broken pair goes unreported until
 * B is touched. Declaring the edges here re-validates the dependents on every change of
 * the source field.
 *
 * A dependent is only re-validated once it is worth judging: it already holds a value, or
 * it already shows an error. Without that guard, picking a client type would immediately
 * flag the IČO field the user has not reached yet.
 */
export function useDependentValidation(form: AnyForm, dependents: DependentFields): void {
    Object.entries(dependents).forEach(([source, targets]) => {
        watch(
            () => (form as unknown as Record<string, unknown>)[source],
            () => {
                targets.forEach((target) => {
                    if (isWorthValidating(form, target)) {
                        callValidate(form, target);
                    }
                });
            },
        );
    });
}

function isWorthValidating(form: AnyForm, field: string): boolean {
    const errors = form.errors as Record<string, string | undefined>;

    if (errors[field]) {
        return true;
    }

    const value = (form as unknown as Record<string, unknown>)[field];

    return value !== null && value !== undefined && value !== '';
}
