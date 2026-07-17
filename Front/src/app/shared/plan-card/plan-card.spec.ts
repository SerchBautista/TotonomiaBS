import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PlanCardComponent } from './plan-card';

describe('PlanCardComponent', () => {
  let fixture: ComponentFixture<PlanCardComponent>;
  let component: PlanCardComponent;

  const plan = {
    id: 'premium' as const,
    name: 'Premium',
    price: '$9.99/mo',
    features: ['Unlimited workspaces', 'Advanced reports'],
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PlanCardComponent],
    }).compileComponents();
  });

  function createFixture(overrides: Partial<PlanCardComponent> = {}): void {
    fixture = TestBed.createComponent(PlanCardComponent);
    component = fixture.componentInstance;
    component.plan = plan;
    Object.assign(component, overrides);
    fixture.detectChanges();
  }

  it('renders plan name, price and features', () => {
    createFixture();
    const text: string = fixture.nativeElement.textContent ?? '';
    expect(text).toContain('Premium');
    expect(text).toContain('$9.99/mo');
    expect(text).toContain('Unlimited workspaces');
  });

  it('shows current plan badge when current is true', () => {
    createFixture({ current: true });

    expect(fixture.nativeElement.textContent).toContain('Plan actual');
    expect(fixture.nativeElement.querySelector('button')).toBeNull();
  });

  it('emits upgrade when premium upgrade button is clicked', () => {
    createFixture();
    const spy = vi.fn();
    component.upgrade.subscribe(spy);

    const button = fixture.nativeElement.querySelector('button') as HTMLButtonElement;
    button.click();

    expect(spy).toHaveBeenCalledOnce();
  });
});
