import { buildPaymentValue, isCard, parsePaymentValue } from './payment-method.model';

describe('payment-method.model helpers', () => {
  it('builds and parses cash payment values', () => {
    expect(buildPaymentValue('cash', null)).toBe('cash');
    expect(parsePaymentValue('cash')).toEqual({
      paymentType: 'cash',
      paymentInstrumentId: null,
    });
  });

  it('builds and parses card payment values', () => {
    expect(buildPaymentValue('card', 'card-1')).toBe('card:card-1');
    expect(parsePaymentValue('card:card-1')).toEqual({
      paymentType: 'card',
      paymentInstrumentId: 'card-1',
    });
  });

  it('builds and parses other payment values', () => {
    expect(buildPaymentValue('other', 'other-1')).toBe('other:other-1');
    expect(parsePaymentValue('other:other-1')).toEqual({
      paymentType: 'other',
      paymentInstrumentId: 'other-1',
    });
  });

  it('returns empty string when instrument id is missing for card/other', () => {
    expect(buildPaymentValue('card', null)).toBe('');
    expect(buildPaymentValue('other', null)).toBe('');
  });

  it('falls back to cash for unknown payment values', () => {
    expect(parsePaymentValue('unknown')).toEqual({
      paymentType: 'cash',
      paymentInstrumentId: null,
    });
  });

  it('identifies card instruments with isCard', () => {
    expect(isCard({ id: '1', workspace_id: 'ws', name: 'Visa', card_type: 'credit', brand: null, last_4_digits: '1234' })).toBe(true);
    expect(isCard({ id: '1', workspace_id: 'ws', name: 'PayPal', description: null })).toBe(false);
  });
});
