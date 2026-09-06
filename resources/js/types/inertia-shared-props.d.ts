// Pure global script (no top-level import/export) — required so this ambient module
// augmentation actually registers against '@inertiajs/core'. That bare specifier is not
// resolvable from resources/js/** (pnpm strict linking only symlinks @inertiajs/vue3 at the
// project root), so the same augmentation placed inside a module file (one with top-level
// import/export, e.g. types/index.d.ts) is silently orphaned and never applied — verified
// empirically against this project's vue-tsc/pnpm setup. Canonical shape lives in
// SharedProps (types/index.d.ts); referenced here via an inline `import()` type query so
// this file itself stays import/export-free.
declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: import('@/types').SharedProps;
    }
}
