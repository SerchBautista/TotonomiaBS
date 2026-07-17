import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { PaymentMethodCreateModalComponent } from './payment-method-create-modal';
import { PaymentMethodsService } from '../../../core/services/payment-methods';
import { ToastService } from '../../../core/services/toast.service';

const ownerWorkspaces = [{ id: 'ws-1', name: 'Casa', owner_id: 'user-1' }];

describe('PaymentMethodCreateModalComponent', () => {
  let fixture: ComponentFixture<PaymentMethodCreateModalComponent>;
  let component: PaymentMethodCreateModalComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PaymentMethodCreateModalComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: PaymentMethodsService,
          useValue: { createMine: vi.fn().mockReturnValue(of({ data: {} })) },
        },
        { provide: ToastService, useValue: { success: vi.fn(), error: vi.fn(), warning: vi.fn() } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PaymentMethodCreateModalComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('open', false);
    fixture.componentRef.setInput('ownerWorkspaces', ownerWorkspaces);
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('renders create form when open', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('#pm-name')).toBeTruthy();
    expect(fixture.nativeElement.querySelector('app-modal-shell .modal-panel')).toBeTruthy();
    expect(component.selectedWorkspaceIds()).toEqual(['ws-1']);
  });
});
