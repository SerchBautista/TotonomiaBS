import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { Router } from '@angular/router';
import { vi } from 'vitest';
import { UpgradePromptComponent } from './upgrade-prompt';

describe('UpgradePromptComponent', () => {
  let fixture: ComponentFixture<UpgradePromptComponent>;
  let component: UpgradePromptComponent;
  let routerSpy: { navigate: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    routerSpy = { navigate: vi.fn() };

    await TestBed.configureTestingModule({
      imports: [UpgradePromptComponent],
      providers: [{ provide: Router, useValue: routerSpy }],
    }).compileComponents();

    fixture = TestBed.createComponent(UpgradePromptComponent);
    component = fixture.componentInstance;
  });

  // 11.2 — UpgradePromptComponent renders inputs and emits upgradeClicked

  it('should create the component', () => {
    fixture.detectChanges();
    expect(component).toBeTruthy();
  });

  it('should render the title input', () => {
    component.title = 'Upgrade to Premium';
    fixture.detectChanges();
    const h3 = fixture.nativeElement.querySelector('h3') as HTMLElement;
    expect(h3.textContent).toContain('Upgrade to Premium');
  });

  it('should render benefits list items', () => {
    component.benefits = ['Benefit A', 'Benefit B'];
    fixture.detectChanges();
    const items = fixture.nativeElement.querySelectorAll('li') as NodeListOf<HTMLElement>;
    expect(items).toHaveLength(2);
    expect(items[0].textContent).toContain('Benefit A');
    expect(items[1].textContent).toContain('Benefit B');
  });

  it('should emit upgradeClicked when button is clicked', () => {
    fixture.detectChanges();
    const spy = vi.fn();
    component.upgradeClicked.subscribe(spy);
    const button = fixture.nativeElement.querySelector('button') as HTMLButtonElement;
    button.click();
    expect(spy).toHaveBeenCalledOnce();
  });

  it('should render empty benefits without list', () => {
    component.benefits = [];
    fixture.detectChanges();
    const ul = fixture.nativeElement.querySelector('ul') as HTMLElement | null;
    expect(ul).toBeNull();
  });

  it('should navigate to /pricing when button is clicked', () => {
    fixture.detectChanges();
    const button = fixture.nativeElement.querySelector('button') as HTMLButtonElement;
    button.click();
    expect(routerSpy.navigate).toHaveBeenCalledWith(['/pricing']);
  });
});
