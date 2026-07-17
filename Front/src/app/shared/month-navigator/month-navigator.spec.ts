import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MonthNavigatorComponent } from './month-navigator';

describe('MonthNavigatorComponent', () => {
  let fixture: ComponentFixture<MonthNavigatorComponent>;
  let component: MonthNavigatorComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MonthNavigatorComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(MonthNavigatorComponent);
    component = fixture.componentInstance;
  });

  it('renders the month label', () => {
    fixture.componentRef.setInput('label', 'Junio de 2026');
    fixture.detectChanges();

    const label = fixture.nativeElement.querySelector('.month-navigator__label') as HTMLElement;
    expect(label.textContent).toBe('Junio de 2026');
  });

  it('emits previous when the left chevron is clicked', () => {
    fixture.componentRef.setInput('label', 'Junio de 2026');
    fixture.detectChanges();

    const spy = vi.fn();
    component.previous.subscribe(spy);

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    buttons[0].click();

    expect(spy).toHaveBeenCalledOnce();
  });

  it('emits next when the right chevron is clicked', () => {
    fixture.componentRef.setInput('label', 'Junio de 2026');
    fixture.detectChanges();

    const spy = vi.fn();
    component.next.subscribe(spy);

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    buttons[buttons.length - 1].click();

    expect(spy).toHaveBeenCalledOnce();
  });

  it('disables the next button when nextDisabled is true', () => {
    fixture.componentRef.setInput('label', 'Junio de 2026');
    fixture.componentRef.setInput('nextDisabled', true);
    fixture.detectChanges();

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    const nextButton = buttons[buttons.length - 1];
    expect(nextButton.disabled).toBe(true);
  });

  it('emits today when the today button is clicked', () => {
    fixture.componentRef.setInput('label', 'Junio de 2026');
    fixture.detectChanges();

    const spy = vi.fn();
    component.today.subscribe(spy);

    const todayButton = fixture.nativeElement.querySelector('.month-navigator__today') as HTMLButtonElement;
    expect(todayButton).toBeTruthy();
    todayButton.click();

    expect(spy).toHaveBeenCalledOnce();
  });

  it('hides the today button when showToday is false', () => {
    fixture.componentRef.setInput('label', 'Junio de 2026');
    fixture.componentRef.setInput('showToday', false);
    fixture.detectChanges();

    const todayButton = fixture.nativeElement.querySelector('.month-navigator__today');
    expect(todayButton).toBeNull();
  });
});
