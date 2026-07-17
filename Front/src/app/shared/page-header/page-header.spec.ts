import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PageHeaderComponent } from './page-header';

describe('PageHeaderComponent', () => {
  let fixture: ComponentFixture<PageHeaderComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PageHeaderComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PageHeaderComponent);
  });

  it('renders the title as an h1', () => {
    fixture.componentRef.setInput('title', 'Gastos');
    fixture.detectChanges();

    const h1 = fixture.nativeElement.querySelector('h1') as HTMLElement;
    expect(h1.textContent).toBe('Gastos');
  });

  it('renders the subtitle when provided', () => {
    fixture.componentRef.setInput('title', 'Gastos');
    fixture.componentRef.setInput('subtitle', 'Workspace personal');
    fixture.detectChanges();

    const subtitle = fixture.nativeElement.querySelector('.page-header__subtitle') as HTMLElement;
    expect(subtitle.textContent).toBe('Workspace personal');
  });

  it('projects the actions slot', () => {
    fixture.componentRef.setInput('title', 'Gastos');
    fixture.detectChanges();

    const actions = fixture.nativeElement.querySelector('.page-header__actions');
    expect(actions).toBeTruthy();
  });
});
