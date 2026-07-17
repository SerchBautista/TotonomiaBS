import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { WorkspaceEditModalComponent } from './workspace-edit-modal';
import { WorkspacesService } from '../../../core/services/workspaces';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { ToastService } from '../../../core/services/toast.service';
import { Workspace } from '../../../core/models/workspace.model';

const mockWorkspace: Workspace = {
  id: 'ws-1',
  owner_id: 'user-uuid-1',
  name: 'My Workspace',
  type: 'personal',
  currency_code: 'USD',
  created_at: '2024-01-01',
  updated_at: '2024-01-01',
};

describe('WorkspaceEditModalComponent', () => {
  let fixture: ComponentFixture<WorkspaceEditModalComponent>;
  let component: WorkspaceEditModalComponent;
  let workspacesServiceMock: { update: ReturnType<typeof vi.fn> };
  const workspaceContextMock = { invalidateCache: vi.fn() };
  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

  beforeEach(async () => {
    workspacesServiceMock = {
      update: vi.fn().mockReturnValue(
        of({
          data: { ...mockWorkspace, name: 'Updated WS', type: 'familiar', currency_code: 'EUR' },
        }),
      ),
    };

    await TestBed.configureTestingModule({
      imports: [WorkspaceEditModalComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: WorkspacesService, useValue: workspacesServiceMock },
        { provide: WorkspaceContextService, useValue: workspaceContextMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(WorkspaceEditModalComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('open', true);
    fixture.componentRef.setInput('workspace', mockWorkspace);
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should call workspacesService.update() on valid submit', () => {
    component.form.setValue({ name: 'Updated WS', type: 'familiar', currency_code: 'EUR' });
    component.submit();

    expect(workspacesServiceMock.update).toHaveBeenCalledWith('ws-1', {
      name: 'Updated WS',
      type: 'familiar',
      currency_code: 'EUR',
    });
    expect(workspaceContextMock.invalidateCache).toHaveBeenCalled();
  });

  it('should emit saved and close on successful submit', () => {
    const savedSpy = vi.fn();
    const closedSpy = vi.fn();
    component.saved.subscribe(savedSpy);
    component.closed.subscribe(closedSpy);

    component.form.setValue({ name: 'Updated WS', type: 'familiar', currency_code: 'EUR' });
    component.submit();

    expect(savedSpy).toHaveBeenCalled();
    expect(closedSpy).toHaveBeenCalled();
  });

  it('should not submit when form is invalid', () => {
    component.form.setValue({ name: '', type: 'personal', currency_code: 'USD' });
    component.submit();
    expect(workspacesServiceMock.update).not.toHaveBeenCalled();
  });

  it('should stop loading when update fails', () => {
    workspacesServiceMock.update.mockReturnValueOnce(throwError(() => ({ status: 500 })));
    component.form.setValue({ name: 'Updated WS', type: 'familiar', currency_code: 'EUR' });
    component.submit();
    expect(component.loading()).toBe(false);
    expect(toastMock.error).not.toHaveBeenCalled();
  });
});
