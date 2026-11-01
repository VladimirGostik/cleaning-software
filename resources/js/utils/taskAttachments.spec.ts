import { describe, expect, it } from 'vitest';

import {
    formatAttachmentLimit,
    taskAttachmentAccept,
    taskAttachmentRejection,
    withCameraName,
} from './taskAttachments';

const constraints: App.Data.Tasks.TaskAttachmentConstraintsData = {
    max_size_kb: 10,
    max_count: 20,
    allowed_mimes: ['image/jpeg', 'image/heic', 'image/heif', 'application/pdf'],
    allowed_extensions: ['jpg', 'heic', 'heif', 'pdf'],
};

function fileOf(name: string, type: string, size = 1): File {
    return new File([new Uint8Array(size)], name, { type });
}

describe('taskAttachmentRejection', () => {
    it('accepts an empty type with a heic extension', () => {
        expect(taskAttachmentRejection(fileOf('IMG_1.HEIC', ''), constraints)).toBeNull();
    });

    it('rejects an empty type with an exe extension', () => {
        expect(taskAttachmentRejection(fileOf('a.exe', ''), constraints)).toBe('invalid_type');
    });

    it('accepts an allowed mime without an extension', () => {
        expect(taskAttachmentRejection(fileOf('scan', 'application/pdf'), constraints)).toBeNull();
    });

    it('accepts exactly the max size and rejects one byte over', () => {
        expect(taskAttachmentRejection(fileOf('a.pdf', 'application/pdf', 10 * 1024), constraints)).toBeNull();
        expect(taskAttachmentRejection(fileOf('a.pdf', 'application/pdf', 10 * 1024 + 1), constraints)).toBe(
            'too_large',
        );
    });
});

describe('taskAttachmentAccept', () => {
    it('omits heic and heif for a coarse pointer', () => {
        const accept = taskAttachmentAccept(constraints, true).split(',');

        expect(accept).toEqual(['image/jpeg', 'application/pdf', '.jpg', '.pdf']);
    });

    it('keeps heic and heif for a fine pointer', () => {
        const accept = taskAttachmentAccept(constraints, false).split(',');

        expect(accept).toEqual(expect.arrayContaining(['image/heic', 'image/heif', '.heic', '.heif']));
    });
});

describe('withCameraName', () => {
    it('builds a unique name per index and keeps the extension, type and size', () => {
        const source = fileOf('image.png', 'image/png', 5);
        const first = withCameraName(source, 1700, 0);
        const second = withCameraName(source, 1700, 1);

        expect(first.name).toBe('photo-1700-0.png');
        expect(second.name).toBe('photo-1700-1.png');
        expect(first.type).toBe('image/png');
        expect(first.size).toBe(5);
    });

    it('falls back to jpg without an extension', () => {
        expect(withCameraName(fileOf('image', 'image/jpeg'), 1, 0).name).toBe('photo-1-0.jpg');
    });
});

describe('formatAttachmentLimit', () => {
    it('drops a trailing .0 from the limit', () => {
        expect(formatAttachmentLimit(10240)).toBe('10 MB');
        expect(formatAttachmentLimit(512)).toBe('512 KB');
    });

    it('keeps a real fraction', () => {
        expect(formatAttachmentLimit(3277)).toBe('3.2 MB');
    });
});
