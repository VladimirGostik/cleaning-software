import { Extension, type CommandProps, type Editor } from '@tiptap/vue-3';

type EditorState = Editor['state'];
type BlockName = 'paragraph' | 'heading';

const HEADING_LEVELS = [1, 2, 3] as const;

export function canSetHeading(state: EditorState): boolean {
    const heading = state.schema.nodes.heading;
    if (!heading) return false;
    const { from, to } = state.selection;
    let possible = false;
    state.doc.nodesBetween(from, to, (node, _pos, parent, index) => {
        if (possible) return false;
        if (!node.isTextblock) return true;
        if (node.type === heading || parent?.canReplaceWith(index, index + 1, heading)) possible = true;

        return false;
    });

    return possible;
}

export function setBlockTypeKeepingParents(name: BlockName, overrides: Record<string, unknown> = {}) {
    return ({ tr, dispatch }: Pick<CommandProps, 'tr' | 'dispatch'>): boolean => {
        const type = tr.doc.type.schema.nodes[name];
        if (!type) return false;
        const { from, to } = tr.selection;
        const targets: { pos: number; end: number; attrs: Record<string, unknown> }[] = [];
        tr.doc.nodesBetween(from, to, (node, pos, parent, index) => {
            if (!node.isTextblock) return true;
            const attrs = {
                ...Object.fromEntries(Object.keys(type.spec.attrs ?? {}).map((key) => [key, node.attrs[key]])),
                ...overrides,
            };
            if (!node.hasMarkup(type, attrs) && parent?.canReplaceWith(index, index + 1, type)) {
                targets.push({ pos, end: pos + node.nodeSize, attrs });
            }

            return false;
        });
        if (targets.length === 0) return false;
        if (dispatch) targets.forEach(({ pos, end, attrs }) => tr.setBlockType(pos, end, type, attrs));

        return true;
    };
}

export const ListSafeHeading = Extension.create({
    name: 'listSafeHeading',
    priority: 110,

    addKeyboardShortcuts() {
        return Object.fromEntries(
            HEADING_LEVELS.map((level) => [
                `Mod-Alt-${level}`,
                () => {
                    const active = this.editor.isActive('heading', { level });
                    this.editor
                        .chain()
                        .command(
                            active
                                ? setBlockTypeKeepingParents('paragraph')
                                : setBlockTypeKeepingParents('heading', { level }),
                        )
                        .run();

                    return true;
                },
            ]),
        );
    },
});
