import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { ActivatedRoute, Router } from '@angular/router';
import { provideRouter } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { vi } from 'vitest';
import { of, throwError } from 'rxjs';
import { HttpErrorResponse } from '@angular/common/http';
import { CategoryListComponent } from './category-list';
import { CategoriesService } from '../../../core/services/categories';
import { ApiService } from '../../../core/services/api';
import { API_SERVICE_TOKEN } from '../../../core/tokens/api-service.token';
import { ToastService } from '../../../core/services/toast.service';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { WorkspaceContextService } from '../../../core/services/workspace-context';
import { environment } from '../../../../environments/environment';

const mockCategoryListResponse = {
  data: [
    { id: 'cat-1', user_id: 'user-1', name: 'Food', icon: 'tag', color: '#ff0000' },
    { id: 'cat-2', user_id: 'user-1', name: 'Transport', icon: 'car', color: '#0000ff' },
  ],
};

describe('CategoryListComponent (user scope)', () => {
  let fixture: ComponentFixture<CategoryListComponent>;
  let component: CategoryListComponent;
  let categoriesServiceMock: {
    listMine: ReturnType<typeof vi.fn>;
    createMine: ReturnType<typeof vi.fn>;
    updateMine: ReturnType<typeof vi.fn>;
    deleteMine: ReturnType<typeof vi.fn>;
    setAsDefaultMine: ReturnType<typeof vi.fn>;
  };
  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
  const authStateMock = { userId: vi.fn().mockReturnValue('user-1') };
  const workspaceContextMock = {
    ensureLoaded: vi.fn().mockResolvedValue([]),
    workspaces: vi.fn().mockReturnValue([{ id: 'ws-1', name: 'Casa', owner_id: 'user-1' }]),
  };

  beforeEach(async () => {
    vi.clearAllMocks();

    categoriesServiceMock = {
      listMine: vi.fn().mockReturnValue(of(mockCategoryListResponse)),
      createMine: vi.fn().mockReturnValue(of({ data: mockCategoryListResponse.data[0] })),
      updateMine: vi.fn().mockReturnValue(
        of({
          data: {
            id: 'cat-1',
            user_id: 'user-1',
            name: 'Food Updated',
            icon: 'fork',
            color: '#ff0000',
          },
        }),
      ),
      deleteMine: vi.fn().mockReturnValue(of({ message: 'deleted' })),
      setAsDefaultMine: vi.fn().mockReturnValue(
        of({
          data: {
            id: 'cat-1',
            user_id: 'user-1',
            name: 'Food',
            icon: 'tag',
            color: '#ff0000',
            is_default: true,
          },
        }),
      ),
    };

    await TestBed.configureTestingModule({
      imports: [CategoryListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: CategoriesService, useValue: categoriesServiceMock },
        { provide: ToastService, useValue: toastMock },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: WorkspaceContextService, useValue: workspaceContextMock },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: { get: vi.fn().mockReturnValue(null) } } },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(CategoryListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('should create the component', () => {
    expect(component).toBeTruthy();
  });

  it('should load user categories via listMine() on init', () => {
    expect(categoriesServiceMock.listMine).toHaveBeenCalledTimes(1);
    expect(component.categories()).toHaveLength(2);
  });

  it('should not read the workspaceId query param from the route', () => {
    // The component must not subscribe to queryParamMap or write ?workspaceId= back.
    // We verify the absence of a Router.navigate call for that query param.
    const router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigate');
    fixture.detectChanges();
    expect(navigateSpy).not.toHaveBeenCalled();
  });

  it('should call categoriesService.createMine() on valid submit', () => {
    component.form.setValue({ name: 'New Cat', icon: 'tag', color: '#16324f' });
    component.submitCreate();
    expect(categoriesServiceMock.createMine).toHaveBeenCalledWith({
      name: 'New Cat',
      icon: 'tag',
      color: '#16324f',
      workspace_ids: ['ws-1'],
    });
  });

  it('should not call createMine when form is invalid', () => {
    component.form.setValue({ name: '', icon: 'tag', color: '#16324f' });
    component.submitCreate();
    expect(categoriesServiceMock.createMine).not.toHaveBeenCalled();
  });

  it('should refresh the list after a successful create', async () => {
    component.form.setValue({ name: 'New Cat', icon: 'tag', color: '#16324f' });
    component.submitCreate();
    await fixture.whenStable();
    expect(categoriesServiceMock.listMine).toHaveBeenCalledTimes(2);
  });

  it('startEdit() should set editingId and populate editForm', () => {
    const cat = mockCategoryListResponse.data[0];
    component.startEdit(cat);
    expect(component.editingId()).toBe('cat-1');
    expect(component.editForm.value).toEqual({ name: 'Food', icon: 'tag', color: '#ff0000' });
  });

  it('cancelEdit() should clear editingId and reset editForm', () => {
    component.startEdit(mockCategoryListResponse.data[0]);
    component.cancelEdit();
    expect(component.editingId()).toBeNull();
  });

  it('submitEdit() should call updateMine() and update categories signal on success', () => {
    component.startEdit(mockCategoryListResponse.data[0]);
    component.editForm.setValue({ name: 'Food Updated', icon: 'fork', color: '#ff0000' });
    component.submitEdit('cat-1');
    expect(categoriesServiceMock.updateMine).toHaveBeenCalledWith('cat-1', {
      name: 'Food Updated',
      icon: 'fork',
      color: '#ff0000',
      workspace_ids: [],
    });
    expect(component.editingId()).toBeNull();
    expect(component.categories()[0].name).toBe('Food Updated');
  });

  it('submitEdit() should not call updateMine() when editForm is invalid', () => {
    component.startEdit(mockCategoryListResponse.data[0]);
    component.editForm.setValue({ name: '', icon: 'tag', color: '#ff0000' });
    component.submitEdit('cat-1');
    expect(categoriesServiceMock.updateMine).not.toHaveBeenCalled();
  });

  it('setCategoryDefault() should call setAsDefaultMine() with the user-scope endpoint', () => {
    component.setCategoryDefault('cat-1');
    expect(categoriesServiceMock.setAsDefaultMine).toHaveBeenCalledWith('cat-1');
  });

  it('clicking the default star button should hit the user-scope endpoint (not workspace)', async () => {
    // Build a fresh TestBed wired to the real CategoriesService + a stubbed HttpClient
    // so we can assert the request URL is /user/categories/.../default.
    TestBed.resetTestingModule();

    const { provideHttpClient, withInterceptorsFromDi } = await import('@angular/common/http');
    const { HttpTestingController, provideHttpClientTesting } =
      await import('@angular/common/http/testing');

    await TestBed.configureTestingModule({
      imports: [CategoryListComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        provideHttpClient(withInterceptorsFromDi()),
        provideHttpClientTesting(),
        { provide: API_SERVICE_TOKEN, useClass: ApiService },
        { provide: ToastService, useValue: toastMock },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: WorkspaceContextService, useValue: workspaceContextMock },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: { get: vi.fn().mockReturnValue(null) } } },
        },
      ],
    }).compileComponents();

    const httpMock = TestBed.inject(HttpTestingController);
    const apiBase = environment.apiUrl;

    const localFixture = TestBed.createComponent(CategoryListComponent);
    const localComponent = localFixture.componentInstance;
    localFixture.detectChanges();

    const listReq = httpMock.expectOne(`${apiBase}/user/categories`);
    expect(listReq.request.method).toBe('GET');
    listReq.flush({ data: mockCategoryListResponse.data });

    localComponent.setCategoryDefault('cat-99');
    const patchReq = httpMock.expectOne(`${apiBase}/user/categories/cat-99/default`);
    expect(patchReq.request.method).toBe('PATCH');
    expect(patchReq.request.url).not.toContain('/workspaces/');
    patchReq.flush({
      data: {
        id: 'cat-99',
        user_id: 'user-1',
        name: 'Food',
        icon: 'tag',
        color: '#ff0000',
        is_default: true,
      },
    });

    httpMock.verify();
  });

  it('confirmDelete() should call deleteMine() and refresh the list', async () => {
    component.requestDelete('cat-1');
    component.confirmDelete();
    await fixture.whenStable();
    expect(categoriesServiceMock.deleteMine).toHaveBeenCalledWith('cat-1', expect.any(Object));
    expect(categoriesServiceMock.listMine).toHaveBeenCalledTimes(2);
  });

  it('cancelDelete() should not call deleteMine()', () => {
    component.requestDelete('cat-1');
    component.cancelDelete();
    expect(categoriesServiceMock.deleteMine).not.toHaveBeenCalled();
  });

  it('confirmDelete() should display server error message when backend returns 409 conflict', () => {
    const serverMessage = 'La categoría está en uso y no se puede eliminar.';
    categoriesServiceMock.deleteMine.mockReturnValue(
      throwError(() => new HttpErrorResponse({
        status: 409,
        error: {
          status: 409,
          code: 'category_in_use',
          message: serverMessage,
          request_id: 'req-123',
        },
      }))
    );

    component.requestDelete('cat-1');
    component.confirmDelete();

    expect(toastMock.error).toHaveBeenCalledWith(
      expect.stringContaining(serverMessage)
    );
  });
});
