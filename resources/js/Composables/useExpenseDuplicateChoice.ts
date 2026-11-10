import { ref } from 'vue';
import { duplicateMessages } from '@/utils/expenseLines';

export function useExpenseDuplicateChoice(resubmit: (action: App.Enums.ExpenseDuplicateActionEnum) => void) {
    const open = ref(false);
    const messages = ref<string[]>([]);

    function offer(errors: Record<string, string>): boolean {
        const found = duplicateMessages(errors);
        if (found.length === 0) return false;

        messages.value = found;
        open.value = true;

        return true;
    }

    function choose(action: App.Enums.ExpenseDuplicateActionEnum): void {
        open.value = false;
        resubmit(action);
    }

    function cancel(): void {
        open.value = false;
    }

    return { open, messages, offer, choose, cancel };
}
