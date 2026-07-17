import { CurrencyFormatPipe, formatCurrency } from './currency-format.pipe';

describe('formatCurrency', () => {
  it('returns em dash for empty values', () => {
    expect(formatCurrency(null)).toBe('—');
    expect(formatCurrency(undefined)).toBe('—');
    expect(formatCurrency('')).toBe('—');
  });

  it('formats numeric values with locale-specific decimals', () => {
    expect(formatCurrency(1234.5, 'USD')).toBe('1,234.50');
    expect(formatCurrency('99.9', 'USD')).toBe('99.90');
  });

  it('includes currency symbol when showSymbol is true', () => {
    const formatted = formatCurrency(100, 'USD', true);
    expect(formatted).toMatch(/\$|US\$/);
    expect(formatted).toContain('100');
  });

  it('uses zero decimals for CLP', () => {
    expect(formatCurrency(1500, 'CLP')).toBe('1.500');
  });
});

describe('CurrencyFormatPipe', () => {
  const pipe = new CurrencyFormatPipe();

  it('delegates transform to formatCurrency', () => {
    expect(pipe.transform(42, 'USD')).toBe('42.00');
    expect(pipe.transform(42, 'USD', true)).toMatch(/\$|US\$/);
  });
});
