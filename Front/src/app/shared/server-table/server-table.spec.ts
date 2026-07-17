import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { ServerTableComponent } from './server-table';

describe('ServerTableComponent', () => {
  let fixture: ComponentFixture<ServerTableComponent>;
  let component: ServerTableComponent;

  const columns = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'email', label: 'Email' },
  ];
  const rows = [
    { id: '1', name: 'Alice', email: 'alice@example.com' },
    { id: '2', name: 'Bob', email: 'bob@example.com' },
  ];

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ServerTableComponent],
      providers: [provideTranslateService({ fallbackLang: 'es', lang: 'es' })],
    }).compileComponents();
  });

  function createFixture(overrides: Partial<ServerTableComponent> = {}): void {
    fixture = TestBed.createComponent(ServerTableComponent);
    component = fixture.componentInstance;
    component.columns = columns;
    component.rows = rows;
    component.editLabelKey = 'common.edit';
    component.deleteLabelKey = 'common.delete';
    component.perPageLabelKey = 'table.per_page';
    component.prevLabelKey = 'table.prev';
    component.nextLabelKey = 'table.next';
    Object.assign(component, overrides);
    fixture.detectChanges();
  }

  it('renders column headers and row data', () => {
    createFixture();
    const tableText: string = fixture.nativeElement.textContent ?? '';

    expect(tableText).toContain('Alice');
    expect(tableText).toContain('Bob');
    expect(tableText).toContain('alice@example.com');
  });

  it('emits sortChanged when a sortable column header is clicked', () => {
    createFixture({ sortBy: 'name', sortDir: 'asc' });
    const spy = vi.fn();
    component.sortChanged.subscribe(spy);

    const sortBtn = fixture.nativeElement.querySelector('.sort-btn') as HTMLButtonElement;
    sortBtn.click();

    expect(spy).toHaveBeenCalledWith({ sortBy: 'name', sortDir: 'desc' });
  });

  it('emits pageChanged when the next page button is clicked', () => {
    createFixture({ currentPage: 1, lastPage: 3 });
    const spy = vi.fn();
    component.pageChanged.subscribe(spy);

    const buttons: HTMLButtonElement[] = Array.from(
      fixture.nativeElement.querySelectorAll('.pager button'),
    );
    const nextBtn = buttons[buttons.length - 1];
    nextBtn.click();

    expect(spy).toHaveBeenCalledWith(2);
  });

  it('emits perPageChanged when the per-page select changes', () => {
    createFixture();
    const spy = vi.fn();
    component.perPageChanged.subscribe(spy);

    const select = fixture.nativeElement.querySelector('#perPage') as HTMLSelectElement;
    select.value = '25';
    select.dispatchEvent(new Event('change'));

    expect(spy).toHaveBeenCalledWith(25);
  });

  it('emits actionClicked when a row action button is clicked', () => {
    createFixture();
    const spy = vi.fn();
    component.actionClicked.subscribe(spy);

    const editBtn = (Array.from(fixture.nativeElement.querySelectorAll('.actions button')) as HTMLButtonElement[]).find(
      (btn) => btn.textContent?.includes('common.edit'),
    );
    editBtn?.click();

    expect(spy).toHaveBeenCalledWith({ action: 'edit', row: rows[0] });
  });

  it('shows empty state when there are no rows', () => {
    createFixture({ rows: [], emptyLabel: 'No records' });

    expect(fixture.nativeElement.textContent).toContain('No records');
  });
});
