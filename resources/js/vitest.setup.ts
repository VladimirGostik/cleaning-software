// Node 22+ global localStorage shadows happy-dom's and throws without --localstorage-file; replaced only when unusable.
function isUsable(storage: Storage | undefined): boolean {
    if (storage === undefined) {
        return false;
    }

    try {
        storage.clear();

        return true;
    } catch {
        return false;
    }
}

function createMemoryStorage(): Storage {
    const store = new Map<string, string>();

    return {
        get length() {
            return store.size;
        },
        clear: () => store.clear(),
        getItem: (key: string) => (store.has(key) ? (store.get(key) as string) : null),
        key: (index: number) => Array.from(store.keys())[index] ?? null,
        removeItem: (key: string) => {
            store.delete(key);
        },
        setItem: (key: string, value: string) => {
            store.set(key, String(value));
        },
    };
}

if (typeof window !== 'undefined' && !isUsable(window.localStorage)) {
    Object.defineProperty(window, 'localStorage', {
        configurable: true,
        value: createMemoryStorage(),
    });
}
