import { FormControl, FormGroup, Validators } from '@angular/forms';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { CrudEntityService, CrudFormFacade } from './crud-form-facade';
import { ToastService } from '../services/toast.service';

interface TestItem {
  id: string;
  name: string;
}

const createMock = vi.fn(() => of({}));
const crudServiceMock: CrudEntityService<TestItem, { name: string }, { name: string }> = {
  getById: vi.fn(() => of({ data: { item: { id: '1', name: 'Loaded' } } })),
  create: createMock,
  update: vi.fn(() => of({})),
};

class TestCrudFacade extends CrudFormFacade<TestItem, { name: string }, { name: string }> {
  protected readonly form = new FormGroup({
    name: new FormControl('', Validators.required),
  });

  protected readonly crudService = crudServiceMock;
  protected readonly loadErrorKey = 'test.load_error';
  protected readonly saveErrorKey = 'test.save_error';
  protected readonly successRoute = '/items';

  protected mapItemToForm(item: TestItem): void {
    this.form.patchValue({ name: item.name });
  }

  protected buildCreatePayload(): { name: string } {
    return { name: this.form.value.name ?? '' };
  }

  protected buildUpdatePayload(): { name: string } {
    return { name: this.form.value.name ?? '' };
  }

  patchName(name: string): void {
    this.form.patchValue({ name });
  }

  submit(): void {
    this.submitCrud();
  }
}

describe('CrudFormFacade', () => {
  let facade: TestCrudFacade;
  const toastMock = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
  const routerMock = { navigateByUrl: vi.fn().mockResolvedValue(true) };

  beforeEach(() => {
    vi.clearAllMocks();
    createMock.mockReturnValue(of({}));

    TestBed.configureTestingModule({
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: ToastService, useValue: toastMock },
        { provide: Router, useValue: routerMock },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              data: { mode: 'create' },
              paramMap: { get: () => null },
            },
          },
        },
      ],
    });

    facade = TestBed.runInInjectionContext(() => new TestCrudFacade());
  });

  it('creates an entity and navigates on successful submit', () => {
    facade.patchName('New item');

    facade.submit();

    expect(createMock).toHaveBeenCalledWith({ name: 'New item' });
    expect(routerMock.navigateByUrl).toHaveBeenCalledWith('/items');
  });

  it('does not submit when the form is invalid', () => {
    facade.patchName('');

    facade.submit();

    expect(createMock).not.toHaveBeenCalled();
  });

  it('shows a toast when save fails', () => {
    createMock.mockReturnValueOnce(throwError(() => new Error('fail')));
    facade.patchName('Broken');

    facade.submit();

    expect(toastMock.error).toHaveBeenCalled();
    expect(routerMock.navigateByUrl).not.toHaveBeenCalled();
  });
});
