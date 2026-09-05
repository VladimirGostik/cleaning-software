export type ToastType = 'success' | 'error' | 'info';

export interface ToastPayload {
    message: string;
    type: ToastType;
}

export function useToast() {
    function showToast(message: string, type: ToastType = 'info') {
        window.dispatchEvent(
            new CustomEvent<ToastPayload>('app-toast', {
                detail: { message, type },
            }),
        );
    }

    function success(message: string) {
        showToast(message, 'success');
    }

    function error(message: string) {
        showToast(message, 'error');
    }

    function info(message: string) {
        showToast(message, 'info');
    }

    return { showToast, success, error, info };
}
