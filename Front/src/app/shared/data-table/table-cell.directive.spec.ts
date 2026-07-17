import { Component, ViewChild } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { TableCellDirective } from './table-cell.directive';

@Component({
  template: `
    <ng-template #cellTpl appTableCell="name" let-row>{{ row }}</ng-template>
  `,
  imports: [TableCellDirective],
})
class HostComponent {
  @ViewChild(TableCellDirective) cellDirective!: TableCellDirective;
}

describe('TableCellDirective', () => {
  let fixture: ComponentFixture<HostComponent>;
  let host: HostComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(HostComponent);
    host = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('exposes the column key through appTableCell input', () => {
    expect(host.cellDirective.appTableCell()).toBe('name');
  });
});
