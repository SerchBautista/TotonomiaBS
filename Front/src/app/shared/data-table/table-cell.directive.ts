import { Directive, inject, input, TemplateRef } from '@angular/core';

@Directive({
  selector: '[appTableCell]',
  standalone: true,
})
export class TableCellDirective<T = unknown> {
  readonly appTableCell = input.required<string>();
  readonly template = inject<TemplateRef<{ $implicit: T }>>(TemplateRef);
}
