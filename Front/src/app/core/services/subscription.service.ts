import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api';
import { CheckoutSession } from '../models/subscription.model';

@Injectable({ providedIn: 'root' })
export class SubscriptionService {
  private readonly api = inject(ApiService);

  initiateCheckout(): Observable<CheckoutSession> {
    return this.api.post<CheckoutSession>('/subscriptions/checkout', {});
  }
}
