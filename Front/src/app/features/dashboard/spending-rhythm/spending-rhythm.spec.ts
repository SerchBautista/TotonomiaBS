import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService, TranslateService } from '@ngx-translate/core';
import { SpendingRhythmComponent } from './spending-rhythm';
import { HeatmapDay } from '../../../core/models/analytics.model';

describe('SpendingRhythmComponent', () => {
  let component: SpendingRhythmComponent;
  let fixture: ComponentFixture<SpendingRhythmComponent>;

  const mockData: HeatmapDay[] = [
    { date: '2026-06-01', total: '100.00', count: 1 },
    { date: '2026-06-02', total: '200.00', count: 2 },
    { date: '2026-06-03', total: '0.00', count: 0 },
  ];

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SpendingRhythmComponent],
      providers: [provideTranslateService({ fallbackLang: 'es', lang: 'es' })],
    }).compileComponents();

    const translate = TestBed.inject(TranslateService);
    translate.setTranslation(
      'es',
      {
        dashboard: {
          day_label: 'Día {{day}}',
          no_data: 'Sin gastos en este mes',
          future_day: 'Futuro',
          expense_count: '{{count}} gasto',
          expenses_count: '{{count}} gastos',
        },
      },
      true,
    );
    translate.use('es');

    fixture = TestBed.createComponent(SpendingRhythmComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('data', mockData);
    fixture.componentRef.setInput('year', 2026);
    fixture.componentRef.setInput('month', 6);
    fixture.componentRef.setInput('currencyCode', 'USD');
    fixture.componentRef.setInput('today', new Date('2026-06-15T00:00:00'));
    fixture.detectChanges();
  });

  it('should calculate monthTotal correctly', () => {
    expect(component.monthTotal()).toBe(300);
  });

  it('should calculate dailyAverage correctly', () => {
    expect(component.dailyAverage()).toBe(150);
  });

  it('should mark future days correctly', () => {
    const futureBars = component.bars().filter((b) => b.isFuture);
    expect(futureBars.length).toBe(15);
    expect(futureBars[0].day).toBe(16);
    expect(futureBars[futureBars.length - 1].day).toBe(30);
  });

  it('should render one bar per day of the month', () => {
    expect(component.bars().length).toBe(30);
  });

  it('should position the average line as a percentage of the max bar', () => {
    const maxTotal = Math.max(...component.bars().map((b) => b.total));
    const expectedPct = (component.dailyAverage() / maxTotal) * 100;
    expect(component.averageLinePct()).toBe(expectedPct);
  });

  it('should render stats and one bar per day in the DOM', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelectorAll('.stat').length).toBe(3);
    expect(compiled.querySelectorAll('rect.bar').length).toBe(30);
    expect(compiled.querySelector('line.average-line')).toBeTruthy();
  });

  it('should mark zero-total days as empty', () => {
    const emptyBars = component.bars().filter((b) => b.isEmpty);
    expect(emptyBars.length).toBe(28);
    expect(emptyBars[0].day).toBe(3);
  });

  it('should not mark any day as future when the selected month is in the past', () => {
    fixture.componentRef.setInput('year', 2026);
    fixture.componentRef.setInput('month', 5);
    fixture.detectChanges();

    const futureBars = component.bars().filter((b) => b.isFuture);
    expect(futureBars.length).toBe(0);
  });

  it('should mark every day as future when the selected month is in the future', () => {
    fixture.componentRef.setInput('year', 2026);
    fixture.componentRef.setInput('month', 8);
    fixture.detectChanges();

    const futureBars = component.bars().filter((b) => b.isFuture);
    expect(futureBars.length).toBe(31);
  });

  it('should render 28 bars for February in a non-leap year', () => {
    fixture.componentRef.setInput('year', 2025);
    fixture.componentRef.setInput('month', 2);
    fixture.detectChanges();

    expect(component.bars().length).toBe(28);
  });

  it('should render 29 bars for February in a leap year', () => {
    fixture.componentRef.setInput('year', 2024);
    fixture.componentRef.setInput('month', 2);
    fixture.detectChanges();

    expect(component.bars().length).toBe(29);
  });

  it('should render 31 bars for December', () => {
    fixture.componentRef.setInput('year', 2026);
    fixture.componentRef.setInput('month', 12);
    fixture.detectChanges();

    expect(component.bars().length).toBe(31);
  });

  it('should position the average line at 0% when all bars are zero', () => {
    fixture.componentRef.setInput('data', []);
    fixture.detectChanges();

    expect(component.monthTotal()).toBe(0);
    expect(component.dailyAverage()).toBe(0);
    expect(component.averageLinePct()).toBe(0);
  });

  it('should render an x-axis label for each day of the month', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelectorAll('.day-label').length).toBe(30);
    expect(compiled.querySelector('.day-label')?.textContent?.trim()).toBe('1');
  });

  it('should make bars keyboard-focusable with tabindex="0"', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const rects = compiled.querySelectorAll('rect.bar');
    expect(rects.length).toBe(30);
    rects.forEach((rect) => {
      expect(rect.getAttribute('tabindex')).toBe('0');
    });
  });

  it('should show tooltip on bar hover and hide on mouseleave', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const rect = compiled.querySelectorAll('rect.bar')[0];

    rect.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
    fixture.detectChanges();

    expect(component.tooltipVisible()).toBe(true);
    expect(component.tooltipBar()?.day).toBe(1);
    expect(compiled.querySelector('.bar-tooltip')).toBeTruthy();

    rect.dispatchEvent(new MouseEvent('mouseleave', { bubbles: true }));
    fixture.detectChanges();

    expect(component.tooltipVisible()).toBe(false);
    expect(compiled.querySelector('.bar-tooltip')).toBeFalsy();
  });

  it('should show tooltip with amount and count on non-empty bars', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const rect = compiled.querySelectorAll('rect.bar')[1];

    rect.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
    fixture.detectChanges();

    const tooltip = compiled.querySelector('.bar-tooltip');
    expect(tooltip).toBeTruthy();
    expect(tooltip?.textContent).toContain('Día 2');
    expect(tooltip?.textContent).toContain('$200.00');
    expect(tooltip?.textContent).toContain('2 gastos');
  });

  it('should show "Sin gastos" tooltip on empty past days', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const rect = compiled.querySelectorAll('rect.bar')[2];

    rect.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
    fixture.detectChanges();

    const tooltip = compiled.querySelector('.bar-tooltip');
    expect(tooltip).toBeTruthy();
    expect(tooltip?.textContent).toContain('Día 3');
    expect(tooltip?.textContent).toContain('Sin gastos en este mes');
  });

  it('should show "Futuro" tooltip on future days', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const rect = compiled.querySelectorAll('rect.bar')[15];

    rect.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
    fixture.detectChanges();

    const tooltip = compiled.querySelector('.bar-tooltip');
    expect(tooltip).toBeTruthy();
    expect(tooltip?.textContent).toContain('Día 16');
    expect(tooltip?.textContent).toContain('Futuro');
  });

  it('should show tooltip on focus and hide on blur', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const rect = compiled.querySelectorAll('rect.bar')[0];

    rect.dispatchEvent(new FocusEvent('focus', { bubbles: false }));
    fixture.detectChanges();

    expect(component.tooltipVisible()).toBe(true);
    expect(component.focusedDay()).toBe(1);

    rect.dispatchEvent(new FocusEvent('blur', { bubbles: false }));
    fixture.detectChanges();

    expect(component.tooltipVisible()).toBe(false);
    expect(component.focusedDay()).toBeNull();
  });
});
