import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { ProjectionData } from '../../../core/models/analytics.model';
import { FinancialProjectionComponent } from './financial-projection';

const mockProjection: ProjectionData = {
  days_elapsed: 10,
  days_in_month: 30,
  current_month_total: 500,
  projected_total: 1500,
  daily_average: 50,
};

describe('FinancialProjectionComponent', () => {
  let fixture: ComponentFixture<FinancialProjectionComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FinancialProjectionComponent],
      providers: [provideTranslateService({ fallbackLang: 'es', lang: 'es' })],
    }).compileComponents();

    fixture = TestBed.createComponent(FinancialProjectionComponent);
    fixture.componentRef.setInput('projection', mockProjection);
    fixture.componentRef.setInput('currencyCode', 'USD');
    fixture.detectChanges();
  });

  it('computes month progress percentage capped at 100', () => {
    const component = fixture.componentInstance;
    expect(component.progressPct()).toBeCloseTo(33.33, 1);
  });

  it('computes days remaining from projection data', () => {
    expect(fixture.componentInstance.daysRemaining()).toBe(20);
  });

  it('computes remaining projected spend', () => {
    expect(fixture.componentInstance.remainingProjected()).toBe(1000);
  });

  it('renders formatted amounts in the template', () => {
    const text: string = fixture.nativeElement.textContent ?? '';
    expect(text).toContain('500');
    expect(text).toContain('1,500');
  });

  it('caps progress at 100% when month is complete', () => {
    fixture.componentRef.setInput('projection', {
      ...mockProjection,
      days_elapsed: 35,
      days_in_month: 30,
    });
    fixture.detectChanges();

    expect(fixture.componentInstance.progressPct()).toBe(100);
  });
});
