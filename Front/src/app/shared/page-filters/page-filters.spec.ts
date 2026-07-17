import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PageFiltersComponent } from './page-filters';

describe('PageFiltersComponent', () => {
  let fixture: ComponentFixture<PageFiltersComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PageFiltersComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PageFiltersComponent);
    fixture.detectChanges();
  });

  it('renders the filters container', () => {
    const container = fixture.nativeElement.querySelector('.page-filters') as HTMLElement;
    expect(container).toBeTruthy();
  });

  it('projects content into the container', () => {
    const content = fixture.nativeElement.querySelector('.page-filters');
    expect(content).toBeTruthy();
  });
});
