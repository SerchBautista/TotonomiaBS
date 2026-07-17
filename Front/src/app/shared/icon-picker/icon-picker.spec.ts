import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { IconPickerComponent } from './icon-picker';

describe('IconPickerComponent', () => {
  let fixture: ComponentFixture<IconPickerComponent>;
  let component: IconPickerComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [IconPickerComponent, ReactiveFormsModule],
    }).compileComponents();

    fixture = TestBed.createComponent(IconPickerComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('updates selected value when an icon is clicked', () => {
    const onChange = vi.fn();
    component.registerOnChange(onChange);

    component.select('car-side');

    expect(component.selected()).toBe('car-side');
    expect(onChange).toHaveBeenCalledWith('car-side');
  });

  it('applies external value through writeValue', () => {
    component.writeValue('wallet');

    expect(component.selected()).toBe('wallet');
  });

  it('defaults to tag when writeValue receives null', () => {
    component.writeValue(null as unknown as string);

    expect(component.selected()).toBe('tag');
  });

  it('does not change value when disabled', () => {
    const onChange = vi.fn();
    component.registerOnChange(onChange);
    component.setDisabledState(true);

    component.select('plane-departure');

    expect(component.selected()).toBe('tag');
    expect(onChange).not.toHaveBeenCalled();
  });

  it('disables icon buttons in the template when setDisabledState is true', () => {
    component.setDisabledState(true);
    fixture.detectChanges();

    const buttons: HTMLButtonElement[] = Array.from(
      fixture.nativeElement.querySelectorAll('.icon-btn'),
    );
    expect(buttons.length).toBeGreaterThan(0);
    expect(buttons.every((button) => button.disabled)).toBe(true);
  });
});
