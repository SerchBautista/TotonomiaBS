import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ConfirmDialogComponent } from './confirm-dialog';

describe('ConfirmDialogComponent', () => {
  let fixture: ComponentFixture<ConfirmDialogComponent>;
  let component: ConfirmDialogComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ConfirmDialogComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(ConfirmDialogComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('title', 'Confirmar acción');
    fixture.componentRef.setInput('message', '¿Estás seguro?');
    fixture.componentRef.setInput('confirmLabel', 'Aceptar');
    fixture.componentRef.setInput('cancelLabel', 'Cancelar');
  });

  it('does not render anything when closed', () => {
    fixture.componentRef.setInput('open', false);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('.overlay')).toBeNull();
  });

  it('renders the dialog with title and message when open', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    const overlay = fixture.nativeElement.querySelector('.overlay') as HTMLElement;
    expect(overlay).toBeTruthy();
    expect(overlay.textContent).toContain('Confirmar acción');
    expect(overlay.textContent).toContain('¿Estás seguro?');
  });

  it('exposes ARIA dialog semantics on the overlay', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    const overlay = fixture.nativeElement.querySelector('.overlay') as HTMLElement;
    expect(overlay.getAttribute('role')).toBe('dialog');
    expect(overlay.getAttribute('aria-modal')).toBe('true');
    expect(overlay.getAttribute('aria-labelledby')).toBeTruthy();

    const titleId = overlay.getAttribute('aria-labelledby');
    const titleEl = fixture.nativeElement.querySelector(`#${titleId}`);
    expect(titleEl).toBeTruthy();
  });

  it('emits confirmed when the confirm button is clicked', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    const spy = vi.fn();
    component.confirmed.subscribe(spy);

    const buttons: HTMLButtonElement[] = Array.from(
      fixture.nativeElement.querySelectorAll('button'),
    );
    const confirmBtn = buttons.find((b) => b.textContent?.trim() === 'Aceptar');
    expect(confirmBtn).toBeTruthy();
    confirmBtn?.click();
    expect(spy).toHaveBeenCalledOnce();
  });

  it('emits canceled when the cancel button is clicked', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    const spy = vi.fn();
    component.canceled.subscribe(spy);

    const buttons: HTMLButtonElement[] = Array.from(
      fixture.nativeElement.querySelectorAll('button'),
    );
    const cancelBtn = buttons.find((b) => b.textContent?.trim() === 'Cancelar');
    expect(cancelBtn).toBeTruthy();
    cancelBtn?.click();
    expect(spy).toHaveBeenCalledOnce();
  });

  it('emits canceled when the backdrop is clicked', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    const spy = vi.fn();
    component.canceled.subscribe(spy);

    const overlay = fixture.nativeElement.querySelector('.overlay') as HTMLElement;
    overlay.click();
    expect(spy).toHaveBeenCalledOnce();
  });

  it('does not emit canceled when the dialog body is clicked', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    const spy = vi.fn();
    component.canceled.subscribe(spy);

    const dialog = fixture.nativeElement.querySelector('.dialog') as HTMLElement;
    dialog.click();
    expect(spy).not.toHaveBeenCalled();
  });

  it('renders cancel button with secondary class and without ghost when open', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    const buttons: HTMLButtonElement[] = Array.from(
      fixture.nativeElement.querySelectorAll('button'),
    );
    const cancelBtn = buttons.find((b) => b.textContent?.trim() === 'Cancelar');
    expect(cancelBtn).toBeTruthy();
    expect(cancelBtn?.classList.contains('secondary')).toBe(true);
    expect(cancelBtn?.classList.contains('ghost')).toBe(false);
  });

  it('renders confirm button with danger class when open', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    const buttons: HTMLButtonElement[] = Array.from(
      fixture.nativeElement.querySelectorAll('button'),
    );
    const confirmBtn = buttons.find((b) => b.textContent?.trim() === 'Aceptar');
    expect(confirmBtn).toBeTruthy();
    expect(confirmBtn?.classList.contains('danger')).toBe(true);
  });

  it('emits canceled when Escape is pressed on the backdrop', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    const spy = vi.fn();
    component.canceled.subscribe(spy);

    const overlay = fixture.nativeElement.querySelector('.overlay') as HTMLElement;
    overlay.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(spy).toHaveBeenCalledOnce();
  });
});
