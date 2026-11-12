import { computed, onScopeDispose, ref, type ComputedRef, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';

import { useToast } from '@/Composables/useToast';
import { formatAttachmentLimit, taskAttachmentRejection } from '@/utils/taskAttachments';

export interface TaskAttachmentUploadItem {
    id: string;
    name: string;
    progress: number;
    status: 'queued' | 'uploading' | 'failed';
    error: string | null;
}

type Outcome = 'uploaded' | 'failed' | 'unknown' | 'cancelled';

export function useTaskAttachmentUpload(options: {
    taskId: string;
    list: () => App.Data.Tasks.TaskAttachmentListData;
}): {
    items: Ref<TaskAttachmentUploadItem[]>;
    uploading: ComputedRef<boolean>;
    add: (files: File[]) => void;
    dismiss: (id: string) => void;
} {
    const { t } = useI18n();
    const toast = useToast();

    const items = ref<TaskAttachmentUploadItem[]>([]);
    const files = new Map<string, File>();
    let counter = 0;
    let running = false;
    const uploadPath = `/tasks/${options.taskId}/attachments`;

    const uploading = computed(() => items.value.some((item) => item.status !== 'failed'));

    function find(id: string): TaskAttachmentUploadItem | undefined {
        return items.value.find((item) => item.id === id);
    }

    function fail(item: TaskAttachmentUploadItem, error: string): void {
        item.status = 'failed';
        item.error = error;
        item.progress = 0;
        files.delete(item.id);
    }

    function row(file: File): TaskAttachmentUploadItem {
        const item: TaskAttachmentUploadItem = {
            id: `upload-${++counter}`,
            name: file.name,
            progress: 0,
            status: 'queued',
            error: null,
        };
        items.value.push(item);

        return item;
    }

    function add(selected: File[]): void {
        const { constraints, attachments } = options.list();
        const active = items.value.filter((item) => item.status !== 'failed').length;
        let remaining = constraints.max_count - attachments.length - active;

        for (const file of selected) {
            const item = row(file);
            const rejection = taskAttachmentRejection(file, constraints);

            if (rejection === 'too_large') {
                fail(item, t('file_too_large', { max: formatAttachmentLimit(constraints.max_size_kb) }));
            } else if (rejection === 'invalid_type') {
                fail(item, t('file_type_not_allowed'));
            } else if (remaining <= 0) {
                fail(item, t('task_attachment_limit_hint', { max: constraints.max_count }));
            } else {
                files.set(item.id, file);
                remaining--;
            }
        }

        void run();
    }

    function dismiss(id: string): void {
        const item = find(id);

        if (item?.status === 'failed') {
            items.value = items.value.filter((candidate) => candidate.id !== id);
        }
    }

    function httpMessage(status: number): string {
        switch (status) {
            case 413:
                return t('file_too_large', { max: formatAttachmentLimit(options.list().constraints.max_size_kb) });
            case 403:
                return t('forbidden');
            case 419:
                return t('session_expired_reload');
            case 429:
                return t('request_throttled');
            default:
                return t('upload_failed');
        }
    }

    function send(item: TaskAttachmentUploadItem): Promise<Outcome> {
        const file = files.get(item.id) as File;

        item.status = 'uploading';

        return new Promise((resolve) => {
            let outcome: Outcome = 'failed';

            router.post(
                uploadPath,
                { file },
                {
                    forceFormData: true,
                    only: ['selected_attachments', 'flash'],
                    preserveState: true,
                    preserveScroll: true,
                    showProgress: false,
                    onProgress: (event: { percentage?: number } | undefined) => {
                        item.progress = event?.percentage ?? 0;
                    },
                    onSuccess: () => {
                        outcome = 'uploaded';
                        items.value = items.value.filter((candidate) => candidate.id !== item.id);
                        files.delete(item.id);
                    },
                    onError: (errors: Record<string, string>) => {
                        fail(item, errors.file ?? Object.values(errors)[0] ?? t('upload_failed'));
                    },
                    onHttpException: (response: { status: number }) => {
                        fail(item, httpMessage(response.status));

                        return false;
                    },
                    onNetworkError: () => {
                        outcome = 'unknown';
                        fail(item, t('task_attachment_unknown_result'));

                        return false;
                    },
                    onCancel: () => {
                        outcome = 'cancelled';
                        fail(item, t('upload_failed'));
                    },
                    onFinish: () => {
                        resolve(outcome);
                    },
                },
            );
        });
    }

    function failQueued(): void {
        for (const item of items.value) {
            if (item.status === 'queued') {
                fail(item, t('upload_failed'));
            }
        }
    }

    async function run(): Promise<void> {
        if (running) {
            return;
        }

        running = true;
        let uploaded = 0;
        let unknown = false;

        const nextQueued = () => items.value.find((item) => item.status === 'queued');

        for (let next = nextQueued(); next; next = nextQueued()) {
            const outcome = await send(next);

            if (outcome === 'uploaded') {
                uploaded++;
            } else if (outcome === 'unknown') {
                unknown = true;
            } else if (outcome === 'cancelled') {
                failQueued();
            }
        }

        running = false;

        if (uploaded > 0) {
            toast.success(t('task_attachment_uploaded', { count: uploaded }));
        }

        if (unknown) {
            router.reload({ only: ['selected_attachments', 'flash'] });
        }
    }

    const removeBeforeListener = router.on(
        'before',
        (event: { detail: { visit: { method: string; url: URL; async: boolean; only: string[] } } }) => {
            const { method, url, async, only } = event.detail.visit;

            if (!uploading.value || (method === 'post' && url.pathname === uploadPath) || (async && only.length > 0)) {
                return;
            }

            return window.confirm(t('task_attachment_leave_confirm'));
        },
    );

    function onBeforeUnload(event: BeforeUnloadEvent): void {
        if (uploading.value) {
            event.preventDefault();
        }
    }

    window.addEventListener('beforeunload', onBeforeUnload);

    onScopeDispose(() => {
        removeBeforeListener();
        window.removeEventListener('beforeunload', onBeforeUnload);
    });

    return { items, uploading, add, dismiss };
}
