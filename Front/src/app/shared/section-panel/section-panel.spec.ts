import { ComponentFixture, TestBed } from '@angular/core/testing';
import { SectionPanelComponent } from './section-panel';

describe('SectionPanelComponent', () => {
  let fixture: ComponentFixture<SectionPanelComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SectionPanelComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(SectionPanelComponent);
  });

  it('renders the panel with content', () => {
    fixture.detectChanges();

    const section = fixture.nativeElement.querySelector('.section-panel') as HTMLElement;
    expect(section).toBeTruthy();
  });

  it('renders the title when provided', () => {
    fixture.componentRef.setInput('title', 'Panel title');
    fixture.detectChanges();

    const title = fixture.nativeElement.querySelector('.section-panel__title') as HTMLElement;
    expect(title.textContent).toBe('Panel title');
  });

  it('projects the actions slot into the header', () => {
    fixture.componentRef.setInput('title', 'Panel title');
    fixture.detectChanges();

    const actionsSlot = fixture.nativeElement.querySelector('.section-panel__actions');
    expect(actionsSlot).toBeTruthy();
  });

  it('applies hover class when withHover is true', () => {
    fixture.componentRef.setInput('withHover', true);
    fixture.detectChanges();

    const section = fixture.nativeElement.querySelector('.section-panel') as HTMLElement;
    expect(section.classList.contains('section-panel--hover')).toBe(true);
  });

  it('removes body padding when noPadding is true', () => {
    fixture.componentRef.setInput('noPadding', true);
    fixture.detectChanges();

    const section = fixture.nativeElement.querySelector('.section-panel') as HTMLElement;
    expect(section.classList.contains('section-panel--no-padding')).toBe(true);
  });
});
