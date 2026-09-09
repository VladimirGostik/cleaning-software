import { watch } from 'vue';
import type { useForm } from '@inertiajs/vue3';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyForm = ReturnType<typeof useForm<any>>;

interface PrecognitiveApi {
    touch: (fields: string[]) => unknown;
    validate: (config: { only: string[] }) => unknown;
}

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
 * `form.validate('due_date')` alone is not enough: the precognition client skips a field
 * whose own value did not change, and due_date did not — only issue_date did. The
 * dependents therefore have to be touched first and then requested explicitly via
 * `only`, which puts them all in one request alongside the field that changed.
 *
 * A dependent is only re-validated once it is worth judging: it already holds a value, or
 * it already shows an error. Without that guard, picking a client type would immediately
 * flag the IČO field the user has not reached yet.
 */
export function useDependentValidation(form: AnyForm, dependents: DependentFields): void {
    Object.entries(dependents).forEach(([source, targets]) => {
        watch(
            () => readField(form, source),
            () => {
                const precognitive = asPrecognitive(form);

                if (!precognitive) {
                    return;
                }

                const pending = targets.filter((target) => isWorthValidating(form, target));

                if (pending.length === 0) {
                    return;
                }

                precognitive.touch(pending);
                precognitive.validate({ only: [source, ...pending] });
            },
        );
    });
}

/** Plain (non-precognitive) Inertia forms have no validate()/touch() — there this is a no-op. */
function asPrecognitive(form: AnyForm): PrecognitiveApi | null {
    const candidate = form as unknown as Partial<PrecognitiveApi>;

    return typeof candidate.touch === 'function' && typeof candidate.validate === 'function'
        ? (candidate as PrecognitiveApi)
        : null;
}

function isWorthValidating(form: AnyForm, field: string): boolean {
    const errors = form.errors as Record<string, string | undefined>;

    if (errors[field]) {
        return true;
    }

    const value = readField(form, field);

    return value !== null && value !== undefined && value !== '';
}

function readField(form: AnyForm, field: string): unknown {
    return (form as unknown as Record<string, unknown>)[field];
}
