import { FormControl } from '@angular/forms';
import { getFirstDayOfMonthInTimezone, getTodayInTimezone, isFutureDate, notFutureDateValidator } from './date-utils';

describe('date-utils', () => {
  it('returns today in the given timezone as YYYY-MM-DD', () => {
    const today = getTodayInTimezone('UTC');
    expect(today).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  });

  it('returns the first day of the current month in the given timezone', () => {
    const firstDay = getFirstDayOfMonthInTimezone('UTC');
    expect(firstDay).toMatch(/^\d{4}-\d{2}-01$/);
  });

  it('detects future dates relative to today in timezone', () => {
    expect(isFutureDate('', 'UTC')).toBe(false);
    expect(isFutureDate('2099-12-31', 'UTC')).toBe(true);
    expect(isFutureDate('2000-01-01', 'UTC')).toBe(false);
  });

  it('validates that a control value is not a future date', () => {
    const validator = notFutureDateValidator('UTC');
    const control = new FormControl('2099-12-31');

    expect(validator(control)).toEqual({ futureDate: true });
    expect(validator(new FormControl(''))).toBeNull();
  });
});
