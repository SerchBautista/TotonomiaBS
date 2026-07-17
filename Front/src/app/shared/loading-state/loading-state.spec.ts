import { ComponentFixture, TestBed } from '@angular/core/testing';
import { LoadingStateComponent } from './loading-state';

describe('LoadingStateComponent', () => {
  let fixture: ComponentFixture<LoadingStateComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LoadingStateComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(LoadingStateComponent);
  });

  it('renders the default loading message', () => {
    fixture.detectChanges();

    const box = fixture.nativeElement.querySelector('.loading-state-box') as HTMLElement;
    expect(box.textContent).toContain('Cargando...');
    expect(box.querySelector('.loading-spinner')).toBeTruthy();
  });

  it('renders a custom loading message', () => {
    fixture.componentRef.setInput('message', 'Cargando gastos...');
    fixture.detectChanges();

    const box = fixture.nativeElement.querySelector('.loading-state-box') as HTMLElement;
    expect(box.textContent).toContain('Cargando gastos...');
  });
});
