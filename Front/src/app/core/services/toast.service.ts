import { inject, Injectable, InjectionToken } from '@angular/core';
import { ToastrService } from 'ngx-toastr';

@Injectable({ providedIn: 'root' })
export class ToastService {
  private readonly toast = inject(ToastrService);

  success(message: string): void {
    this.toast.success(message);
  }

  error(message: string): void {
    this.toast.error(message);
  }

  info(message: string): void {
    this.toast.info(message);
  }

  warning(message: string): void {
    this.toast.warning(message);
  }
}

export const TOAST_SERVICE_TOKEN = new InjectionToken<ToastService>('TOAST_SERVICE_TOKEN', {
  providedIn: 'root',
  factory: () => inject(ToastService)
});
