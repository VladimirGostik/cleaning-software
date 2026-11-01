import { formatBytes } from '@/utils/bytes';

type Constraints = App.Data.Tasks.TaskAttachmentConstraintsData;

const HEIC_MIMES = ['image/heic', 'image/heif'];
const HEIC_EXTENSIONS = ['heic', 'heif'];

function extensionOf(name: string): string | null {
    const dot = name.lastIndexOf('.');

    return dot > -1 && dot < name.length - 1 ? name.slice(dot + 1).toLowerCase() : null;
}

export function taskAttachmentRejection(file: File, constraints: Constraints): 'too_large' | 'invalid_type' | null {
    const extension = extensionOf(file.name);
    const typeAllowed = constraints.allowed_mimes.includes(file.type);
    const extensionAllowed = extension !== null && constraints.allowed_extensions.includes(extension);

    if (!typeAllowed && !extensionAllowed) {
        return 'invalid_type';
    }

    return file.size > constraints.max_size_kb * 1024 ? 'too_large' : null;
}

export function taskAttachmentAccept(constraints: Constraints, coarsePointer: boolean): string {
    const mimes = constraints.allowed_mimes.filter((mime) => !coarsePointer || !HEIC_MIMES.includes(mime));
    const extensions = constraints.allowed_extensions
        .filter((extension) => !coarsePointer || !HEIC_EXTENSIONS.includes(extension))
        .map((extension) => `.${extension}`);

    return [...mimes, ...extensions].join(',');
}

export function withCameraName(file: File, epochMs: number, index: number): File {
    const extension = extensionOf(file.name) ?? 'jpg';

    return new File([file], `photo-${epochMs}-${index}.${extension}`, {
        type: file.type,
        lastModified: file.lastModified,
    });
}

export function formatAttachmentLimit(maxSizeKb: number): string {
    return formatBytes(maxSizeKb * 1024).replace(/\.0 /, ' ');
}
