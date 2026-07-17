import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { CtaBannerComponent } from './cta-banner';

describe('CtaBannerComponent', () => {
  let fixture: ComponentFixture<CtaBannerComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CtaBannerComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(CtaBannerComponent);
    const component = fixture.componentInstance;
    component.title = 'Join today';
    component.subtitle = 'Start for free';
    component.primaryCta = 'Register';
    component.secondaryCta = 'Pricing';
    fixture.detectChanges();
  });

  it('renders title, subtitle and CTA links', () => {
    const el: HTMLElement = fixture.nativeElement;
    expect(el.textContent).toContain('Join today');
    expect(el.textContent).toContain('Start for free');

    const links = el.querySelectorAll('a');
    expect(links.length).toBe(2);
    expect(links[0].textContent?.trim()).toBe('Register');
    expect(links[1].textContent?.trim()).toBe('Pricing');
  });
});
