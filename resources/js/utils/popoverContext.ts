import type { InjectionKey } from 'vue';

export interface PopoverNode {
    isInside: (node: Node) => boolean;
    isOpen: () => boolean;
}

export interface PopoverContext {
    register: (node: PopoverNode) => () => void;
}

export const POPOVER_CONTEXT: InjectionKey<PopoverContext> = Symbol('anchored-popover');
