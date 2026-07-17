import { Pipe, PipeTransform } from '@angular/core';

const CURRENCY_LOCALE: Record<string, string> = {
  USD: 'en-US',
  MXN: 'es-MX',
  EUR: 'de-DE',
  ARS: 'es-AR',
  CLP: 'es-CL',
  COP: 'es-CO',
  BRL: 'pt-BR',
};

const NO_DECIMALS_CURRENCIES = new Set(['CLP']);

export function formatCurrency(
  value: number | string | null | undefined,
  currencyCode: string = 'USD',
  showSymbol: boolean = false,
): string {
  if (value === null || value === undefined || value === '') return '—';
  const num = typeof value === 'string' ? parseFloat(value) : value;
  if (isNaN(num)) return String(value);

  const locale = CURRENCY_LOCALE[currencyCode] ?? 'en-US';
  const decimals = NO_DECIMALS_CURRENCIES.has(currencyCode) ? 0 : 2;

  if (showSymbol) {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currencyCode,
      currencyDisplay: 'narrowSymbol',
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }).format(num);
  }

  return new Intl.NumberFormat(locale, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(num);
}

@Pipe({
  name: 'currencyFormat',
  standalone: true,
  pure: true,
})
export class CurrencyFormatPipe implements PipeTransform {
  transform(
    value: number | string | null | undefined,
    currencyCode: string = 'USD',
    showSymbol: boolean = false,
  ): string {
    return formatCurrency(value, currencyCode, showSymbol);
  }
}
