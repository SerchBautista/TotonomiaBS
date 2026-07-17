import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { AdminLoginResult, AuthApiService } from './auth-api.service';

@Injectable({
  providedIn: 'root'
})
export class AdminAuthService {
  private readonly authApiService = inject(AuthApiService);

  login(email: string, password: string): Observable<AdminLoginResult> {
    return this.authApiService.loginAsAdmin(email, password);
  }
}
