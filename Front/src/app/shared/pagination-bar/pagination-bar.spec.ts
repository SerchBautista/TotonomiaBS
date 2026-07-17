import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PaginationBarComponent } from './pagination-bar';

describe('PaginationBarComponent', () => {
  let fixture: ComponentFixture<PaginationBarComponent>;
  let component: PaginationBarComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PaginationBarComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PaginationBarComponent);
    component = fixture.componentInstance;
  });

  it('renders the current page indicator', () => {
    fixture.componentRef.setInput('currentPage', 2);
    fixture.componentRef.setInput('lastPage', 5);
    fixture.detectChanges();

    const indicator = fixture.nativeElement.querySelector('.pagination-bar__indicator') as HTMLElement;
    expect(indicator.textContent?.trim()).toBe('2 / 5');
  });

  it('disables the previous button on the first page', () => {
    fixture.componentRef.setInput('currentPage', 1);
    fixture.componentRef.setInput('lastPage', 5);
    fixture.detectChanges();

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    expect(buttons[0].disabled).toBe(true);
    expect(buttons[1].disabled).toBe(false);
  });

  it('disables the next button on the last page', () => {
    fixture.componentRef.setInput('currentPage', 5);
    fixture.componentRef.setInput('lastPage', 5);
    fixture.detectChanges();

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    expect(buttons[0].disabled).toBe(false);
    expect(buttons[1].disabled).toBe(true);
  });

  it('emits prev when the previous button is clicked', () => {
    fixture.componentRef.setInput('currentPage', 3);
    fixture.componentRef.setInput('lastPage', 5);
    fixture.detectChanges();

    const spy = vi.fn();
    component.prev.subscribe(spy);

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    buttons[0].click();

    expect(spy).toHaveBeenCalledOnce();
  });

  it('emits next when the next button is clicked', () => {
    fixture.componentRef.setInput('currentPage', 3);
    fixture.componentRef.setInput('lastPage', 5);
    fixture.detectChanges();

    const spy = vi.fn();
    component.next.subscribe(spy);

    const buttons = fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>;
    buttons[1].click();

    expect(spy).toHaveBeenCalledOnce();
  });
});
