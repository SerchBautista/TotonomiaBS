import { ComponentFixture, TestBed } from '@angular/core/testing';
import { EmptyStateComponent } from './empty-state';

describe('EmptyStateComponent', () => {
  let fixture: ComponentFixture<EmptyStateComponent>;
  let component: EmptyStateComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [EmptyStateComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(EmptyStateComponent);
    component = fixture.componentInstance;
  });

  it('renders the title and default icon', () => {
    fixture.componentRef.setInput('title', 'Sin registros');
    fixture.detectChanges();

    const title = fixture.nativeElement.querySelector('.empty-state-box__title') as HTMLElement;
    const icon = fixture.nativeElement.querySelector('.fa-inbox');
    expect(title.textContent).toBe('Sin registros');
    expect(icon).toBeTruthy();
  });

  it('renders a custom icon', () => {
    fixture.componentRef.setInput('title', 'Sin datos');
    fixture.componentRef.setInput('icon', 'fa-folder-open');
    fixture.detectChanges();

    const icon = fixture.nativeElement.querySelector('.fa-folder-open');
    expect(icon).toBeTruthy();
  });

  it('renders the message when provided', () => {
    fixture.componentRef.setInput('title', 'Sin registros');
    fixture.componentRef.setInput('message', 'Agrega un gasto para empezar');
    fixture.detectChanges();

    const message = fixture.nativeElement.querySelector('.empty-state-box__message') as HTMLElement;
    expect(message.textContent).toBe('Agrega un gasto para empezar');
  });

  it('renders the action button and emits action when clicked', () => {
    fixture.componentRef.setInput('title', 'Sin registros');
    fixture.componentRef.setInput('actionLabel', 'Crear gasto');
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('button') as HTMLButtonElement;
    expect(button).toBeTruthy();
    expect(button.textContent?.trim()).toBe('Crear gasto');

    const spy = vi.fn();
    component.action.subscribe(spy);
    button.click();

    expect(spy).toHaveBeenCalledOnce();
  });
});
