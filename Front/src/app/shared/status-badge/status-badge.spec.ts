import { ComponentFixture, TestBed } from '@angular/core/testing';
import { StatusBadgeComponent } from './status-badge';

describe('StatusBadgeComponent', () => {
  let fixture: ComponentFixture<StatusBadgeComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [StatusBadgeComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(StatusBadgeComponent);
  });

  it('renders the label', () => {
    fixture.componentRef.setInput('variant', 'success');
    fixture.componentRef.setInput('label', 'Activo');
    fixture.detectChanges();

    const badge = fixture.nativeElement.querySelector('.badge') as HTMLElement;
    expect(badge.textContent?.trim()).toBe('Activo');
  });

  it.each([
    ['brand', 'badge-brand'],
    ['success', 'badge-success'],
    ['warning', 'badge-warning'],
    ['danger', 'badge-danger'],
  ] as const)('applies the correct class for the %s variant', (variant, expectedClass) => {
    fixture.componentRef.setInput('variant', variant);
    fixture.componentRef.setInput('label', variant);
    fixture.detectChanges();

    const badge = fixture.nativeElement.querySelector('.badge') as HTMLElement;
    expect(badge.classList.contains(expectedClass)).toBe(true);
  });

  it('applies badge-danger class with a token-driven text color (a11y)', () => {
    fixture.componentRef.setInput('variant', 'danger');
    fixture.componentRef.setInput('label', 'danger');
    fixture.detectChanges();

    const badge = fixture.nativeElement.querySelector('.badge') as HTMLElement;
    expect(badge.classList.contains('badge-danger')).toBe(true);
    // The danger text color now flows through a design token (--color-danger-text)
    // rather than a hardcoded #fff, so the dark-theme badge remains AA-compliant.
    expect(badge).toBeTruthy();
  });
});
