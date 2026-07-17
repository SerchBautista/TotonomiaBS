import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { SpendingBreakdownDetailModalComponent } from './spending-breakdown-detail-modal';
import { CategorySummary } from '../../../core/models/analytics.model';

describe('SpendingBreakdownDetailModalComponent', () => {
  let component: SpendingBreakdownDetailModalComponent;
  let fixture: ComponentFixture<SpendingBreakdownDetailModalComponent>;

  const mockCategories: CategorySummary[] = [
    { id: '1', name: 'A', icon: null, color: null, total: '500.00', count: 1 },
    { id: '2', name: 'B', icon: null, color: null, total: '300.00', count: 1 },
    { id: '3', name: 'C', icon: null, color: null, total: '200.00', count: 1 },
  ];

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SpendingBreakdownDetailModalComponent],
      providers: [provideTranslateService({ fallbackLang: 'es', lang: 'es' })],
    }).compileComponents();

    fixture = TestBed.createComponent(SpendingBreakdownDetailModalComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('categories', mockCategories);
    fixture.componentRef.setInput('filter', 'all');
    fixture.componentRef.setInput('currencyCode', 'USD');
    fixture.detectChanges();
  });

  it('should show all categories sorted by total desc', () => {
    const cats = component.filteredCategories();
    expect(cats.length).toBe(3);
    expect(cats[0].name).toBe('A');
    expect(cats[1].name).toBe('B');
    expect(cats[2].name).toBe('C');
  });

  it('should filter only "others" when filter is others', () => {
    fixture.componentRef.setInput('filter', 'others');
    fixture.componentRef.setInput('topCount', 1);
    fixture.detectChanges();
    const cats = component.filteredCategories();
    expect(cats.length).toBe(2);
    expect(cats[0].name).toBe('B');
  });

  it('should emit closed when the modal shell closes', () => {
    let emitted = false;
    component.closed.subscribe(() => (emitted = true));

    const closeButton = fixture.nativeElement.querySelector(
      '.modal-panel__close',
    ) as HTMLButtonElement;
    expect(closeButton).toBeTruthy();
    closeButton.click();

    expect(emitted).toBe(true);
  });

  it('should display the total in the footer', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const totalEl = compiled.querySelector('.modal-total');
    expect(totalEl?.textContent).toContain('1,000');
  });

  it('should render empty state when filtered categories are empty', () => {
    fixture.componentRef.setInput('categories', []);
    fixture.componentRef.setInput('filter', 'all');
    fixture.detectChanges();

    const emptyMessage = (fixture.nativeElement as HTMLElement).querySelector('.empty');
    expect(emptyMessage).toBeTruthy();
    expect(component.total()).toBe(0);
  });

  it('should precalculate display items with value and percentage', () => {
    const items = component.displayItems();
    expect(items.length).toBe(3);
    expect(items[0].value).toBe(500);
    expect(items[0].percentage).toBeCloseTo(50);
    expect(items[1].percentage).toBeCloseTo(30);
    expect(items[2].percentage).toBeCloseTo(20);
  });

  it('should return 0% for every display item when the modal total is zero', () => {
    fixture.componentRef.setInput('categories', [
      { id: '1', name: 'A', icon: null, color: null, total: '0.00', count: 0 },
    ]);
    fixture.detectChanges();

    expect(component.total()).toBe(0);
    const items = component.displayItems();
    expect(items[0].percentage).toBe(0);
  });

  it('should use fallback color for categories without color', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const colorSwatch = compiled.querySelector('.detail-color');
    expect(colorSwatch).toBeTruthy();
    const bg = (colorSwatch as HTMLElement).style.background;
    expect(bg).toBe('var(--color-text-muted)');
  });

  it('should expose the modal shell with the right ARIA roles', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const backdrop = compiled.querySelector('.modal-backdrop');
    expect(backdrop?.getAttribute('role')).toBe('dialog');
    expect(backdrop?.getAttribute('aria-modal')).toBe('true');
  });
});
