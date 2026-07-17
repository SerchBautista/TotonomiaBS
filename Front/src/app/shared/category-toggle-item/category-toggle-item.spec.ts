import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { Category } from '../../core/models/category.model';
import { CategoryToggleItemComponent } from './category-toggle-item';

const category: Category = {
  id: 'cat-1',
  user_id: 'user-1',
  name: 'Food',
  icon: 'fa-tag',
  color: '#ff0000',
};

describe('CategoryToggleItemComponent', () => {
  let fixture: ComponentFixture<CategoryToggleItemComponent>;
  let component: CategoryToggleItemComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CategoryToggleItemComponent],
      providers: [provideTranslateService({ fallbackLang: 'es', lang: 'es' })],
    }).compileComponents();

    fixture = TestBed.createComponent(CategoryToggleItemComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('category', category);
    fixture.componentRef.setInput('enabled', false);
    fixture.detectChanges();
  });

  it('renders category name', () => {
    expect(fixture.nativeElement.textContent).toContain('Food');
  });

  it('emits toggled with inverted enabled state', () => {
    const spy = vi.fn();
    component.toggled.subscribe(spy);

    component.toggle();

    expect(spy).toHaveBeenCalledWith(true);
  });

  it('disables toggle button while toggling', () => {
    fixture.componentRef.setInput('toggling', true);
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('.toggle-btn') as HTMLButtonElement;
    expect(button.disabled).toBe(true);
  });
});
