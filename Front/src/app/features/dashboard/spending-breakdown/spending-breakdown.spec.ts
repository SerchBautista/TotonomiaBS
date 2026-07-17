import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { SpendingBreakdownComponent } from './spending-breakdown';
import { CategorySummary } from '../../../core/models/analytics.model';

describe('SpendingBreakdownComponent', () => {
  let component: SpendingBreakdownComponent;
  let fixture: ComponentFixture<SpendingBreakdownComponent>;

  const mockCategories: CategorySummary[] = [
    { id: '1', name: 'Comida', icon: 'fa-utensils', color: '#FF6B6B', total: '500.00', count: 5 },
    { id: '2', name: 'Transporte', icon: 'fa-car', color: '#4ECDC4', total: '300.00', count: 3 },
  ];

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SpendingBreakdownComponent],
      providers: [provideTranslateService({ fallbackLang: 'es', lang: 'es' })],
    }).compileComponents();

    fixture = TestBed.createComponent(SpendingBreakdownComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('categories', mockCategories);
    fixture.componentRef.setInput('currencyCode', 'USD');
    fixture.detectChanges();
  });

  it('should calculate total correctly', () => {
    expect(component.total()).toBe(800);
  });

  it('should calculate percentages correctly', () => {
    const cats = component.displayCategories();
    expect(cats[0].percentage).toBeCloseTo(62.5);
    expect(cats[1].percentage).toBeCloseTo(37.5);
  });

  it('should sort categories by total descending before grouping', () => {
    const unorderedCategories: CategorySummary[] = [
      { id: '1', name: 'Low', icon: null, color: null, total: '100.00', count: 1 },
      { id: '2', name: 'High', icon: null, color: null, total: '900.00', count: 1 },
      { id: '3', name: 'Mid', icon: null, color: null, total: '500.00', count: 1 },
    ];
    fixture.componentRef.setInput('categories', unorderedCategories);
    fixture.detectChanges();

    const cats = component.displayCategories();
    expect(cats[0].name).toBe('High');
    expect(cats[1].name).toBe('Mid');
    expect(cats[2].name).toBe('Low');
  });

  it('should group categories > 7 in Others', () => {
    const manyCategories: CategorySummary[] = Array.from({ length: 10 }, (_, i) => ({
      id: String(i),
      name: `Cat ${i}`,
      icon: null,
      color: null,
      total: String((10 - i) * 100),
      count: 1,
    }));
    fixture.componentRef.setInput('categories', manyCategories);
    fixture.detectChanges();
    const cats = component.displayCategories();
    expect(cats.length).toBe(8);
    expect(cats[7].isOther).toBe(true);
    expect(cats[7].color).toBe('var(--color-text-muted)');
  });

  it('should emit openDetail with others filter when the user clicks on Others in the DOM', () => {
    const manyCategories: CategorySummary[] = Array.from({ length: 10 }, (_, i) => ({
      id: String(i),
      name: `Cat ${i}`,
      icon: null,
      color: null,
      total: '100.00',
      count: 1,
    }));
    fixture.componentRef.setInput('categories', manyCategories);
    fixture.detectChanges();

    let emitted: { filter: 'all' | 'others' } | undefined;
    component.openDetail.subscribe((e) => (emitted = e));

    const othersItem = (fixture.nativeElement as HTMLElement).querySelector(
      '.category-item.clickable',
    );
    expect(othersItem).toBeTruthy();
    (othersItem as HTMLElement).click();

    expect(emitted).toEqual({ filter: 'others' });
  });

  it('should emit openDetail with all filter when the user clicks View all in the DOM', () => {
    fixture.detectChanges();

    let emitted: { filter: 'all' | 'others' } | undefined;
    component.openDetail.subscribe((e) => (emitted = e));

    const viewAllButton = (fixture.nativeElement as HTMLElement).querySelector(
      '.breakdown-footer .btn',
    );
    expect(viewAllButton).toBeTruthy();
    (viewAllButton as HTMLElement).click();

    expect(emitted).toEqual({ filter: 'all' });
  });

  it('should not emit openDetail when a regular category is clicked', () => {
    fixture.detectChanges();

    let emitted: { filter: 'all' | 'others' } | undefined;
    component.openDetail.subscribe((e) => (emitted = e));

    const regularItem = (fixture.nativeElement as HTMLElement).querySelectorAll(
      '.category-item',
    )[0];
    (regularItem as HTMLElement).click();

    expect(emitted).toBeUndefined();
  });

  it('should show empty state in the DOM when there are no categories', () => {
    fixture.componentRef.setInput('categories', []);
    fixture.detectChanges();

    expect(component.displayCategories().length).toBe(0);
    const emptyMessage = (fixture.nativeElement as HTMLElement).querySelector('.empty');
    expect(emptyMessage).toBeTruthy();
  });

  it('should compute donut segments with cumulative percentages', () => {
    const segments = component.donutSegments();
    expect(segments.length).toBe(2);
    expect(segments[0].startPct).toBe(0);
    expect(segments[0].endPct).toBeCloseTo(62.5);
    expect(segments[1].startPct).toBeCloseTo(62.5);
    expect(segments[1].endPct).toBeCloseTo(100);
  });

  it('should use fallback colors when category color is null', () => {
    fixture.componentRef.setInput('categories', [
      { id: '1', name: 'No Color', icon: null, color: null, total: '100.00', count: 1 },
    ]);
    fixture.detectChanges();

    const cats = component.displayCategories();
    expect(cats[0].color).toBeTruthy();
    expect(cats[0].color).not.toBeNull();
  });

  it('should calculate zero percentages when the total is zero', () => {
    fixture.componentRef.setInput('categories', [
      { id: '1', name: 'A', icon: null, color: '#FF6B6B', total: '0.00', count: 0 },
      { id: '2', name: 'B', icon: null, color: '#4ECDC4', total: '0.00', count: 0 },
    ]);
    fixture.detectChanges();

    expect(component.total()).toBe(0);
    for (const cat of component.displayCategories()) {
      expect(cat.percentage).toBe(0);
    }
  });
});
