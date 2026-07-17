import { ComponentFixture, TestBed } from '@angular/core/testing';
import { describe, expect, it } from 'vitest';
import { WorkspaceSelectorListComponent } from './workspace-selector-list';

describe('WorkspaceSelectorListComponent', () => {
  it('emits the next selection when a checkbox changes', async () => {
    await TestBed.configureTestingModule({
      imports: [WorkspaceSelectorListComponent],
    }).compileComponents();

    const fixture: ComponentFixture<WorkspaceSelectorListComponent> = TestBed.createComponent(
      WorkspaceSelectorListComponent,
    );
    const component = fixture.componentInstance;
    const emitted: string[][] = [];

    fixture.componentRef.setInput('workspaces', [
      {
        id: 'ws-1',
        owner_id: 'user-1',
        name: 'Casa',
        type: 'personal',
        currency_code: 'USD',
        created_at: '',
        updated_at: '',
      },
    ]);
    fixture.componentRef.setInput('selectedIds', []);
    component.selectedIdsChange.subscribe((value) => emitted.push(value));
    fixture.detectChanges();

    const checkbox = fixture.nativeElement.querySelector('input') as HTMLInputElement;
    checkbox.checked = true;
    checkbox.dispatchEvent(new Event('change'));

    expect(emitted).toEqual([['ws-1']]);
  });
});
