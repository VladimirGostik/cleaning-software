import { inject, provide, type InjectionKey } from 'vue';
import type { useForm } from '@inertiajs/vue3';

export type InertiaForm = ReturnType<typeof useForm>;

const FormContextKey: InjectionKey<InertiaForm> = Symbol('FormContext');

export function provideFormContext(form: InertiaForm): void {
    provide(FormContextKey, form);
}

export function useFormContext(): InertiaForm | undefined {
    return inject(FormContextKey, undefined);
}

export function callValidate(form: InertiaForm | undefined, field: string | undefined): void {
    if (!form || !field) {
        return;
    }
    if (typeof (form as unknown as Record<string, unknown>).validate === 'function') {
        (form as unknown as Record<string, (f: string) => void>).validate(field);
    }
}
