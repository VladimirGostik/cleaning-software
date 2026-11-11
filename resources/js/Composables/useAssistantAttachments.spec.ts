import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, type EffectScope } from 'vue';
import { ASSISTANT_IMAGE_MAX_BYTES } from '@/utils/assistant';
import { useAssistantAttachments } from './useAssistantAttachments';

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string, params?: Record<string, unknown>) => (params ? `${key}:${JSON.stringify(params)}` : key),
    }),
}));

let scope: EffectScope;
const create = vi.fn(() => 'blob:x');
const revoke = vi.fn();

function image(name = 'a.jpg', size = 10): File {
    return new File([new Uint8Array(size)], name, { type: 'image/jpeg' });
}

function setup() {
    return scope.run(() => useAssistantAttachments())!;
}

beforeEach(() => {
    scope = effectScope();
    let n = 0;
    create.mockImplementation(() => `blob:${++n}`);
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: create, revokeObjectURL: revoke }));
});

afterEach(() => {
    scope.stop();
    create.mockReset();
    revoke.mockReset();
});

describe('useAssistantAttachments', () => {
    it('keeps five files and reports too_many for the sixth', () => {
        const files = setup();

        files.add(Array.from({ length: 6 }, (_, i) => image(`${i}.jpg`)));

        expect(files.items.value).toHaveLength(5);
        expect(files.isFull.value).toBe(true);
        expect(files.error.value).toBe('assistant_attachment_too_many:{"max":5}');
    });

    it('refuses a wrong type and an image over 5 MB with a translated reason', () => {
        const files = setup();

        files.add([new File(['x'], 'a.heic', { type: 'image/heic' })]);
        expect(files.error.value).toBe('assistant_attachment_invalid_type');

        files.add([image('big.jpg', ASSISTANT_IMAGE_MAX_BYTES + 1)]);
        expect(files.error.value).toBe('assistant_file_too_large:{"max":5}');
        expect(files.items.value).toHaveLength(0);
    });

    it('clears the error on the next successful add', () => {
        const files = setup();
        files.add([new File(['x'], 'a.docx', { type: 'application/msword' })]);

        files.add([image()]);

        expect(files.error.value).toBeNull();
    });

    it('gives a PDF no preview and an image an object URL', () => {
        const files = setup();

        files.add([new File(['x'], 'a.pdf', { type: 'application/pdf' }), image()]);

        expect(files.items.value.map((i) => [i.kind, i.previewUrl])).toEqual([
            ['pdf', null],
            ['image', 'blob:1'],
        ]);
    });

    it('revokes the object URL on remove', () => {
        const files = setup();
        files.add([image()]);

        files.remove(files.items.value[0].id);

        expect(revoke).toHaveBeenCalledWith('blob:1');
        expect(files.items.value).toEqual([]);
    });

    it('take returns the files and empties the list', () => {
        const files = setup();
        const a = image('a.jpg');
        files.add([a]);

        expect(files.take()).toEqual([a]);
        expect(files.items.value).toEqual([]);
        expect(revoke).toHaveBeenCalledWith('blob:1');
    });

    it('revokes every URL when the scope is disposed', () => {
        const files = setup();
        files.add([image('a.jpg'), image('b.jpg')]);

        scope.stop();

        expect(revoke).toHaveBeenCalledTimes(2);
    });
});
