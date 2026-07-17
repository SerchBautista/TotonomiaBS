import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { provideRouter } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { CrudFormComponent } from './crud-form';

describe('CrudFormComponent', () => {
  let fixture: ComponentFixture<CrudFormComponent>;
  let component: CrudFormComponent;
  let formGroup: FormGroup;

  beforeEach(async () => {
    formGroup = new FormGroup({
      name: new FormControl('', Validators.required),
    });

    await TestBed.configureTestingModule({
      imports: [CrudFormComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
      ],
    }).compileComponents();
  });

  function createFixture(overrides: Partial<CrudFormComponent> = {}): void {
    fixture = TestBed.createComponent(CrudFormComponent);
    component = fixture.componentInstance;
    component.entityKey = 'administrators';
    component.mode = 'create';
    component.backLabelKey = 'administrators.back_to_list';
    component.submitLabelKey = 'administrators.save';
    component.formGroup = formGroup;
    Object.assign(component, overrides);
    fixture.detectChanges();
  }

  it('renders the resolved title in the header', () => {
    createFixture();
    const title = fixture.nativeElement.querySelector('h2') as HTMLElement;

    expect(title.textContent).toContain('administrators.form_create_title');
  });

  it('emits submitted when the form is submitted', () => {
    createFixture();
    const spy = vi.fn();
    component.submitted.subscribe(spy);

    const form = fixture.nativeElement.querySelector('form') as HTMLFormElement;
    form.dispatchEvent(new Event('submit'));

    expect(spy).toHaveBeenCalledOnce();
  });

  it('disables submit button when disableSubmit is true', () => {
    createFixture({ disableSubmit: true });

    const submitBtn = fixture.nativeElement.querySelector('button[type="submit"]') as HTMLButtonElement;
    expect(submitBtn.disabled).toBe(true);
  });

  it('uses custom titleKey when provided', () => {
    createFixture({ titleKey: 'custom.title' });

    const title = fixture.nativeElement.querySelector('h2') as HTMLElement;
    expect(title.textContent).toContain('custom.title');
  });
});
