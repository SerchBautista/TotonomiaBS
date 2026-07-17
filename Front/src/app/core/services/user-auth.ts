import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { AuthApiService, UserLoginResult } from './auth-api.service';

@Injectable({
  providedIn: 'root'
})
export class UserAuthService {
  private readonly authApiService = inject(AuthApiService);

  login(email: string, password: string): Observable<UserLoginResult> {
    return this.authApiService.loginAsUser(email, password);
  }
}
