import type { useForm } from '@inertiajs/vue3';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyForm = ReturnType<typeof useForm<any>>;

export const VALIDATION_DEBOUNCE_MS = 1500;

// Inertia exposes no cancel(); resetting the timeout recreates the debounced validator and drops its trailing call.
export function cancelPendingValidation(form: AnyForm): void {
    const precognitive = form as unknown as { setValidationTimeout?: (ms: number) => unknown };

    precognitive.setValidationTimeout?.(VALIDATION_DEBOUNCE_MS);
}
