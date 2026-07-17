import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ModalShellComponent } from './modal-shell';

function queryModalBackdrop(): HTMLElement | null {
  return document.body.querySelector('.modal-backdrop');
}

function queryModalPanel(): HTMLElement | null {
  return document.body.querySelector('.modal-panel');
}

describe('ModalShellComponent', () => {
  let fixture: ComponentFixture<ModalShellComponent>;
  let component: ModalShellComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ModalShellComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(ModalShellComponent);
    component = fixture.componentInstance;
  });

  afterEach(() => {
    if (fixture) {
      fixture.componentRef.setInput('open', false);
      fixture.detectChanges();
      fixture.destroy();
    }
  });

  it('renders the modal when open', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('title', 'Modal title');
    fixture.detectChanges();

    const backdrop = queryModalBackdrop();
    const title = document.body.querySelector('.modal-panel__title') as HTMLElement;
    expect(backdrop).toBeTruthy();
    expect(title.textContent).toBe('Modal title');
  });

  it('attaches the modal backdrop to document.body when open', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('title', 'Modal title');
    fixture.detectChanges();

    const backdrop = queryModalBackdrop();
    expect(backdrop).toBeTruthy();
    expect(document.body.contains(backdrop)).toBe(true);
    expect(fixture.nativeElement.querySelector('.modal-backdrop')).toBeNull();
    expect(document.body.querySelector('.modal-shell-portal-host')).toBeTruthy();
  });

  it('does not render the modal when closed', () => {
    fixture.componentRef.setInput('open', false);
    fixture.componentRef.setInput('title', 'Modal title');
    fixture.detectChanges();

    const backdrop = queryModalBackdrop();
    expect(backdrop).toBeNull();
  });

  it('has the correct ARIA attributes', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('title', 'Modal title');
    fixture.detectChanges();

    const backdrop = queryModalBackdrop() as HTMLElement;
    expect(backdrop.getAttribute('role')).toBe('dialog');
    expect(backdrop.getAttribute('aria-modal')).toBe('true');
    expect(backdrop.getAttribute('aria-labelledby')).toBe('modal-title');
  });

  it('emits close when the close button is clicked', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('title', 'Modal title');
    fixture.detectChanges();

    const spy = vi.fn();
    component.close.subscribe(spy);

    const closeButton = document.body.querySelector('.modal-panel__close') as HTMLButtonElement;
    closeButton.click();

    expect(spy).toHaveBeenCalledOnce();
  });

  it('emits close when the backdrop is clicked', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('title', 'Modal title');
    fixture.detectChanges();

    const spy = vi.fn();
    component.close.subscribe(spy);

    const backdrop = queryModalBackdrop() as HTMLElement;
    backdrop.click();

    expect(spy).toHaveBeenCalledOnce();
  });

  it('does not emit close when the panel is clicked', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('title', 'Modal title');
    fixture.detectChanges();

    const spy = vi.fn();
    component.close.subscribe(spy);

    const panel = queryModalPanel() as HTMLElement;
    panel.click();

    expect(spy).not.toHaveBeenCalled();
  });

  it('emits close when Escape is pressed', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('title', 'Modal title');
    fixture.detectChanges();

    const spy = vi.fn();
    component.close.subscribe(spy);

    const event = new KeyboardEvent('keydown', { key: 'Escape' });
    const backdrop = queryModalBackdrop() as HTMLElement;
    backdrop.dispatchEvent(event);

    expect(spy).toHaveBeenCalledOnce();
  });

  it('projects body and footer content', () => {
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('title', 'Modal title');
    fixture.detectChanges();

    expect(document.body.querySelector('.modal-panel__body')).toBeTruthy();
    expect(document.body.querySelector('.modal-panel__footer')).toBeTruthy();
  });
});
