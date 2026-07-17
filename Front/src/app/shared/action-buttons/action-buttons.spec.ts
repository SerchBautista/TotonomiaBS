import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActionButtonsComponent } from './action-buttons';

describe('ActionButtonsComponent', () => {
  let fixture: ComponentFixture<ActionButtonsComponent>;
  let component: ActionButtonsComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ActionButtonsComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(ActionButtonsComponent);
    component = fixture.componentInstance;
  });

  it('renders edit and delete buttons by default', () => {
    fixture.detectChanges();

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    expect(buttons.length).toBe(2);
    expect(buttons[0].querySelector('.fa-pen')).toBeTruthy();
    expect(buttons[1].querySelector('.fa-trash')).toBeTruthy();
  });

  it('renders the view button when showView is true', () => {
    fixture.componentRef.setInput('showView', true);
    fixture.detectChanges();

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    expect(buttons.length).toBe(3);
    expect(buttons[0].querySelector('.fa-eye')).toBeTruthy();
  });

  it('hides the edit button when showEdit is false', () => {
    fixture.componentRef.setInput('showEdit', false);
    fixture.detectChanges();

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    expect(buttons.length).toBe(1);
    expect(buttons[0].querySelector('.fa-trash')).toBeTruthy();
  });

  it('emits edit when the edit button is clicked', () => {
    fixture.detectChanges();

    const spy = vi.fn();
    component.edit.subscribe(spy);

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    buttons[0].click();

    expect(spy).toHaveBeenCalledOnce();
  });

  it('emits delete when the delete button is clicked', () => {
    fixture.detectChanges();

    const spy = vi.fn();
    component.delete.subscribe(spy);

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    buttons[1].click();

    expect(spy).toHaveBeenCalledOnce();
  });

  it('emits view when the view button is clicked', () => {
    fixture.componentRef.setInput('showView', true);
    fixture.detectChanges();

    const spy = vi.fn();
    component.view.subscribe(spy);

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    buttons[0].click();

    expect(spy).toHaveBeenCalledOnce();
  });

  it('sets translatable aria-labels on icon buttons', () => {
    fixture.componentRef.setInput('editAriaLabel', 'Editar gasto');
    fixture.componentRef.setInput('deleteAriaLabel', 'Eliminar gasto');
    fixture.detectChanges();

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    expect(buttons[0].getAttribute('aria-label')).toBe('Editar gasto');
    expect(buttons[1].getAttribute('aria-label')).toBe('Eliminar gasto');
  });
});
