import { Injectable } from '@angular/core';
import { Subject } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class QuickAddExpenseService {
  private readonly openSubject = new Subject<void>();
  private readonly createdSubject = new Subject<string>();
  readonly open$ = this.openSubject.asObservable();
  readonly created$ = this.createdSubject.asObservable();

  open(): void {
    this.openSubject.next();
  }

  notifyCreated(workspaceId: string): void {
    this.createdSubject.next(workspaceId);
  }
}
