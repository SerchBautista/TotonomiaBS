import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CategoryBadgeComponent } from './category-badge';
import { contrastColor } from '../utils/contrast-color';

describe('CategoryBadgeComponent', () => {
  let fixture: ComponentFixture<CategoryBadgeComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CategoryBadgeComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(CategoryBadgeComponent);
  });

  it('renders the category name', () => {
    fixture.componentRef.setInput('category', { name: 'Comida', color: '#ff6b6b' });
    fixture.detectChanges();

    const badge = fixture.nativeElement.querySelector('.category-badge') as HTMLElement;
    expect(badge.textContent).toContain('Comida');
  });

  it('renders the icon when showIcon is true and icon is provided', () => {
    fixture.componentRef.setInput('category', { name: 'Comida', color: '#ff6b6b', icon: 'fa-utensils' });
    fixture.detectChanges();

    const icon = fixture.nativeElement.querySelector('.category-badge__icon');
    expect(icon).toBeTruthy();
  });

  it('hides the icon when showIcon is false', () => {
    fixture.componentRef.setInput('category', { name: 'Comida', color: '#ff6b6b', icon: 'fa-utensils' });
    fixture.componentRef.setInput('showIcon', false);
    fixture.detectChanges();

    const icon = fixture.nativeElement.querySelector('.category-badge__icon');
    expect(icon).toBeNull();
  });

  it('uses white text on dark colors', () => {
    fixture.componentRef.setInput('category', { name: 'Navy', color: '#122140' });
    fixture.detectChanges();

    const badge = fixture.nativeElement.querySelector('.category-badge') as HTMLElement;
    expect(badge.style.color).toBe('rgb(255, 255, 255)');
  });

  it('uses black text on light colors', () => {
    fixture.componentRef.setInput('category', { name: 'Yellow', color: '#fbbf24' });
    fixture.detectChanges();

    const badge = fixture.nativeElement.querySelector('.category-badge') as HTMLElement;
    expect(badge.style.color).toBe('rgb(0, 0, 0)');
  });
});

describe('contrastColor', () => {
  it('returns white for dark colors', () => {
    expect(contrastColor('#000000')).toBe('#ffffff');
    expect(contrastColor('#122140')).toBe('#ffffff');
  });

  it('returns black for light colors', () => {
    expect(contrastColor('#ffffff')).toBe('#000000');
    expect(contrastColor('#fbbf24')).toBe('#000000');
  });

  it('returns black for mid-gray colors above the threshold', () => {
    expect(contrastColor('#999999')).toBe('#000000');
  });
});
