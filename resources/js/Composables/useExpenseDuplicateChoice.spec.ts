import { describe, expect, it, vi } from 'vitest';
import { useExpenseDuplicateChoice } from './useExpenseDuplicateChoice';

describe('useExpenseDuplicateChoice', () => {
    it('does not open without a duplicate error', () => {
        const choice = useExpenseDuplicateChoice(vi.fn());

        expect(choice.offer({ 'lines.0.amount': 'bad' })).toBe(false);
        expect(choice.open.value).toBe(false);
    });

    it('opens with the conflict messages', () => {
        const choice = useExpenseDuplicateChoice(vi.fn());

        expect(choice.offer({ duplicate: 'generic', 'lines.0.tenant_membership_id': 'Anna 10/2026: 900.00' })).toBe(
            true,
        );
        expect(choice.open.value).toBe(true);
        expect(choice.messages.value).toEqual(['Anna 10/2026: 900.00']);
    });

    it('closes and resubmits with the chosen action', () => {
        const resubmit = vi.fn();
        const choice = useExpenseDuplicateChoice(resubmit);
        choice.offer({ duplicate: 'generic' });

        choice.choose('replace');

        expect(choice.open.value).toBe(false);
        expect(resubmit).toHaveBeenCalledWith('replace');
    });

    it('cancel closes without resubmitting', () => {
        const resubmit = vi.fn();
        const choice = useExpenseDuplicateChoice(resubmit);
        choice.offer({ duplicate: 'generic' });

        choice.cancel();

        expect(choice.open.value).toBe(false);
        expect(resubmit).not.toHaveBeenCalled();
    });
});
