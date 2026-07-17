import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ContentHeroComponent } from './content-hero';

describe('ContentHeroComponent', () => {
  let fixture: ComponentFixture<ContentHeroComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ContentHeroComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(ContentHeroComponent);
    const component = fixture.componentInstance;
    component.title = 'Main title';
    component.lead = 'Supporting copy';
    component.eyebrow = 'Eyebrow';
    component.primaryCta = 'Get started';
    component.primaryCtaLink = '/register';
    fixture.detectChanges();
  });

  it('renders title, lead and primary CTA link', () => {
    const el: HTMLElement = fixture.nativeElement;
    expect(el.textContent).toContain('Main title');
    expect(el.textContent).toContain('Supporting copy');
    expect(el.textContent).toContain('Eyebrow');

    const link = el.querySelector('a.btn.primary') as HTMLAnchorElement;
    expect(link.textContent?.trim()).toBe('Get started');
    expect(link.getAttribute('href')).toContain('/register');
  });
});
