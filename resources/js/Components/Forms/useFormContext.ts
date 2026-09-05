import { inject, provide } from 'vue';
import { useForm } from '@inertiajs/vue3';

const FORM_KEY = Symbol('form');

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyForm = ReturnType<typeof useForm<any>>;

export function provideForm(form: AnyForm): void {
    provide(FORM_KEY, form);
}

export function useFormContext(): AnyForm | null {
    return inject<AnyForm>(FORM_KEY) ?? null;
}
