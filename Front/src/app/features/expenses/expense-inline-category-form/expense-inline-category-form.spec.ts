import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CategoriesService } from '../../../core/services/categories';
import { ToastService } from '../../../core/services/toast.service';
import { ExpenseInlineCategoryFormComponent } from './expense-inline-category-form';

describe('ExpenseInlineCategoryFormComponent', () => {
  let fixture: ComponentFixture<ExpenseInlineCategoryFormComponent>;
  let categoriesServiceMock: { createMine: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    categoriesServiceMock = {
      createMine: vi.fn().mockReturnValue(
        of({
          data: {
            id: 'cat-new',
            user_id: 'user-1',
            name: 'Nueva categoría',
            icon: 'tag',
            color: '#16324f',
          },
        }),
      ),
    };

    await TestBed.configureTestingModule({
      imports: [ExpenseInlineCategoryFormComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: CategoriesService, useValue: categoriesServiceMock },
        {
          provide: ToastService,
          useValue: { success: vi.fn(), error: vi.fn() },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ExpenseInlineCategoryFormComponent);
    fixture.componentRef.setInput('workspaceId', 'ws-1');
    fixture.componentRef.setInput('ownerWorkspaces', [
      { id: 'ws-1', owner_id: 'user-1', name: 'Workspace', type: 'personal', currency_code: 'USD', created_at: '', updated_at: '' },
    ]);
    fixture.componentRef.setInput('canSelectAdditionalWorkspaces', false);
    fixture.componentRef.setInput('initialWorkspaceIds', ['ws-1']);
    fixture.detectChanges();
  });

  it('always includes the current workspace in category creation payload', () => {
    fixture.componentRef.setInput('canSelectAdditionalWorkspaces', true);
    fixture.componentRef.setInput('initialWorkspaceIds', ['ws-2']);
    fixture.detectChanges();

    fixture.componentInstance.updateWorkspaceSelection(['ws-2']);
    fixture.componentInstance.form.patchValue({
      name: 'Nueva categoría',
      icon: 'tag',
      color: '#16324f',
    });

    fixture.componentInstance.submit();

    expect(categoriesServiceMock.createMine).toHaveBeenCalledWith(
      expect.objectContaining({
        workspace_ids: ['ws-2', 'ws-1'],
      }),
      expect.any(Object),
    );
  });

  it('emits created when category is saved successfully', () => {
    const createdSpy = vi.fn();
    fixture.componentInstance.created.subscribe(createdSpy);

    fixture.componentInstance.form.patchValue({
      name: 'Nueva categoría',
      icon: 'tag',
      color: '#16324f',
    });
    fixture.componentInstance.submit();

    expect(createdSpy).toHaveBeenCalledWith(
      expect.objectContaining({ id: 'cat-new', name: 'Nueva categoría' }),
    );
  });
});
