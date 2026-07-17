import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormCardComponent } from './form-card';

describe('FormCardComponent', () => {
  let fixture: ComponentFixture<FormCardComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FormCardComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(FormCardComponent);
  });

  it('renders the card container', () => {
    fixture.detectChanges();

    const card = fixture.nativeElement.querySelector('.form-card') as HTMLElement;
    expect(card).toBeTruthy();
  });

  it('renders the title when provided', () => {
    fixture.componentRef.setInput('title', 'Datos del gasto');
    fixture.detectChanges();

    const title = fixture.nativeElement.querySelector('.form-card__title') as HTMLElement;
    expect(title.textContent).toBe('Datos del gasto');
  });

  it('projects content into the card body', () => {
    fixture.detectChanges();

    const content = fixture.nativeElement.querySelector('.form-card__content');
    expect(content).toBeTruthy();
  });
});
