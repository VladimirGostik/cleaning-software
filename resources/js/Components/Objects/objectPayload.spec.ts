import { describe, expect, it } from 'vitest';

import { billingToFormData, emptyBillingFormData } from './objectPayload';

describe('billingToFormData', () => {
    it('maps a null billing to empty strings for every billing key', () => {
        expect(billingToFormData(null)).toEqual(emptyBillingFormData());
        expect(Object.values(emptyBillingFormData()).every((v) => v === '')).toBe(true);
    });

    it('maps billing values and nulls to strings', () => {
        const form = billingToFormData({
            name: 'Bytový dom A',
            ico: '123',
            dic: null,
            vat_number: null,
            peppol_participant_id: '0245:2012345678',
            street: null,
            city: 'Žilina',
            postal_code: null,
            email: 'fakt@dom.sk',
        });

        expect(form).toEqual({
            billing_name: 'Bytový dom A',
            billing_ico: '123',
            billing_dic: '',
            billing_vat_number: '',
            billing_peppol_participant_id: '0245:2012345678',
            billing_street: '',
            billing_city: 'Žilina',
            billing_postal_code: '',
            billing_email: 'fakt@dom.sk',
        });
    });

    it('maps a null billing name to an empty string', () => {
        const form = billingToFormData({
            name: null,
            ico: null,
            dic: null,
            vat_number: null,
            peppol_participant_id: null,
            street: null,
            city: null,
            postal_code: null,
            email: 'x@y.sk',
        });

        expect(form.billing_name).toBe('');
        expect(form.billing_email).toBe('x@y.sk');
    });
});
