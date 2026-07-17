import { TestBed } from '@angular/core/testing';
import { ToastrService } from 'ngx-toastr';
import { vi } from 'vitest';
import { ToastService } from './toast.service';

describe('ToastService', () => {
  let service: ToastService;
  let toastrMock: {
    success: ReturnType<typeof vi.fn>;
    error: ReturnType<typeof vi.fn>;
    info: ReturnType<typeof vi.fn>;
    warning: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    toastrMock = {
      success: vi.fn(),
      error: vi.fn(),
      info: vi.fn(),
      warning: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [ToastService, { provide: ToastrService, useValue: toastrMock }],
    });

    service = TestBed.inject(ToastService);
  });

  it('delegates success messages to ToastrService', () => {
    service.success('Saved');
    expect(toastrMock.success).toHaveBeenCalledWith('Saved');
  });

  it('delegates error messages to ToastrService', () => {
    service.error('Failed');
    expect(toastrMock.error).toHaveBeenCalledWith('Failed');
  });

  it('delegates info and warning messages to ToastrService', () => {
    service.info('Note');
    service.warning('Careful');
    expect(toastrMock.info).toHaveBeenCalledWith('Note');
    expect(toastrMock.warning).toHaveBeenCalledWith('Careful');
  });
});
