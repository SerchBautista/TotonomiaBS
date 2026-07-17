import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

export function getTodayInTimezone(timezone: string): string {
  const formatter = new Intl.DateTimeFormat('sv-SE', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  });
  return formatter.format(new Date());
}

export function getFirstDayOfMonthInTimezone(timezone: string): string {
  const now = new Date();
  const formatter = new Intl.DateTimeFormat('sv-SE', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
  });
  return formatter.format(now) + '-01';
}

export function isFutureDate(date: string, timezone: string): boolean {
  if (!date) return false;
  const today = getTodayInTimezone(timezone);
  return date > today;
}

export function notFutureDateValidator(timezone: string): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    if (!control.value) return null;
    const today = getTodayInTimezone(timezone);
    return control.value > today ? { futureDate: true } : null;
  };
}
