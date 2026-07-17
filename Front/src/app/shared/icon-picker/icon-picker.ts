import { ChangeDetectionStrategy, Component, forwardRef, input, signal } from '@angular/core';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';

export const CATEGORY_ICONS: string[] = [
  'tag',
  'house',
  'car-side',
  'utensils',
  'cart-shopping',
  'plane-departure',
  'heart-pulse',
  'graduation-cap',
  'gamepad',
  'bolt',
  'shirt',
  'dumbbell',
  'mug-hot',
  'paw',
  'baby-carriage',
  'music',
  'book',
  'wallet',
  'piggy-bank',
  'briefcase',
  'gift',
  'mobile-screen',
  'tv',
  'bus',
  'bicycle',
  'screwdriver-wrench',
  'scissors',
  'spa',
  'pizza-slice',
  'film',
];

@Component({
  selector: 'app-icon-picker',
  templateUrl: './icon-picker.html',
  styleUrl: './icon-picker.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [
    {
      provide: NG_VALUE_ACCESSOR,
      useExisting: forwardRef(() => IconPickerComponent),
      multi: true,
    },
  ],
})
export class IconPickerComponent implements ControlValueAccessor {
  readonly icons = CATEGORY_ICONS;
  readonly selected = signal<string>('tag');
  readonly disabled = signal(false);
  readonly ariaLabel = input<string | null>(null);
  readonly ariaLabelledBy = input<string | null>(null);

  private onChange: (value: string) => void = () => {};
  private onTouched: () => void = () => {};

  select(icon: string): void {
    if (this.disabled()) return;
    this.selected.set(icon);
    this.onChange(icon);
    this.onTouched();
  }

  writeValue(value: string): void {
    this.selected.set(value ?? 'tag');
  }

  registerOnChange(fn: (value: string) => void): void {
    this.onChange = fn;
  }

  registerOnTouched(fn: () => void): void {
    this.onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    this.disabled.set(isDisabled);
  }
}
