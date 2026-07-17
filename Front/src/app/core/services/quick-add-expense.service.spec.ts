import { TestBed } from '@angular/core/testing';
import { firstValueFrom } from 'rxjs';
import { QuickAddExpenseService } from './quick-add-expense.service';

describe('QuickAddExpenseService', () => {
  let service: QuickAddExpenseService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(QuickAddExpenseService);
  });

  it('emits on open$ when open is called', async () => {
    const promise = firstValueFrom(service.open$);
    service.open();
    await expect(promise).resolves.toBeUndefined();
  });

  it('emits workspace id on created$ when notifyCreated is called', async () => {
    const promise = firstValueFrom(service.created$);
    service.notifyCreated('ws-42');
    await expect(promise).resolves.toBe('ws-42');
  });
});
