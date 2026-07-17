import { computed, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Subject } from 'rxjs';
import { NavigationEnd, Router } from '@angular/router';
import { WorkspaceContextService } from '../../core/services/workspace-context';
import { WorkspaceSwitcherComponent } from './workspace-switcher';

type WorkspaceOption = { id: string; name: string };

describe('WorkspaceSwitcherComponent', () => {
  let fixture: ComponentFixture<WorkspaceSwitcherComponent>;

  const workspacesState = signal<WorkspaceOption[]>([]);
  const currentWorkspaceIdState = signal<string | null>(null);

  const workspaceContextMock = {
    workspaces: workspacesState.asReadonly(),
    currentWorkspaceId: currentWorkspaceIdState.asReadonly(),
    selectedWorkspace: computed(() =>
      workspacesState().find((ws) => ws.id === currentWorkspaceIdState()) ?? null
    ),
    setCurrentWorkspaceId: vi.fn((id: string | null) => currentWorkspaceIdState.set(id)),
  };

  const routerEvents = new Subject<NavigationEnd>();
  const routerMock = {
    url: '/user/dashboard',
    events: routerEvents.asObservable(),
  };

  async function setup(workspaceList: WorkspaceOption[], url = '/user/dashboard') {
    TestBed.resetTestingModule();
    workspacesState.set(workspaceList);
    currentWorkspaceIdState.set(workspaceList[0]?.id ?? null);
    workspaceContextMock.setCurrentWorkspaceId.mockClear();
    routerMock.url = url;

    await TestBed.configureTestingModule({
      imports: [WorkspaceSwitcherComponent],
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: WorkspaceContextService, useValue: workspaceContextMock },
        { provide: Router, useValue: routerMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(WorkspaceSwitcherComponent);
    fixture.detectChanges();
  }

  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    fixture?.destroy();
  });

  it('is created', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ]);

    expect(fixture.componentInstance).toBeTruthy();
  });

  it('is hidden when only one workspace is available', async () => {
    await setup([{ id: 'ws-1', name: 'Personal' }]);

    expect(fixture.nativeElement.querySelector('select')).toBeNull();
    expect(fixture.nativeElement.querySelector('label')).toBeNull();
  });

  it('is visible on a user route when more than one workspace is available', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ]);

    const select = fixture.nativeElement.querySelector('select') as HTMLSelectElement;
    const label = fixture.nativeElement.querySelector('label');
    expect(select).toBeTruthy();
    expect(label).toBeTruthy();
  });

  it('is visible for an admin on a user route', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ], '/user/dashboard');

    const select = fixture.nativeElement.querySelector('select') as HTMLSelectElement;
    const label = fixture.nativeElement.querySelector('label');
    expect(select).toBeTruthy();
    expect(label).toBeTruthy();
  });

  it('is hidden on an admin route', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ], '/admin/dashboard');

    expect(fixture.nativeElement.querySelector('select')).toBeNull();
    expect(fixture.nativeElement.querySelector('label')).toBeNull();
  });

  it('renders the current workspace as the selected option', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ]);
    currentWorkspaceIdState.set('ws-2');
    fixture.detectChanges();

    const select = fixture.nativeElement.querySelector('select') as HTMLSelectElement;
    expect(select.value).toBe('ws-2');

    const options = Array.from(select.querySelectorAll('option')) as HTMLOptionElement[];
    const selected = options.find((option) => option.selected);
    expect(selected?.value).toBe('ws-2');
  });

  it('calls workspaceContext.setCurrentWorkspaceId when the user changes the selection', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ]);

    const select = fixture.nativeElement.querySelector('select') as HTMLSelectElement;
    select.value = 'ws-2';
    select.dispatchEvent(new Event('change'));
    fixture.detectChanges();

    expect(workspaceContextMock.setCurrentWorkspaceId).toHaveBeenCalledWith('ws-2');
  });

  it('hides the label when showLabel is false', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ]);

    fixture.componentRef.setInput('showLabel', false);
    fixture.detectChanges();

    const select = fixture.nativeElement.querySelector('select') as HTMLSelectElement;
    const label = fixture.nativeElement.querySelector('label');
    expect(select).toBeTruthy();
    expect(label).toBeNull();
  });

  it('is hidden when the URL contains /user/workspaces/', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ], '/user/workspaces/ws-1/budgets');

    expect(fixture.nativeElement.querySelector('select')).toBeNull();
    expect(fixture.nativeElement.querySelector('label')).toBeNull();
  });

  it('is visible after leaving a workspace route', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ], '/user/workspaces/ws-1/budgets');

    expect(fixture.nativeElement.querySelector('select')).toBeNull();

    routerEvents.next(new NavigationEnd(1, '/user/dashboard', '/user/dashboard'));
    fixture.detectChanges();

    const select = fixture.nativeElement.querySelector('select') as HTMLSelectElement;
    const label = fixture.nativeElement.querySelector('label');
    expect(select).toBeTruthy();
    expect(label).toBeTruthy();
  });

  it('renders the mobile button with current workspace name', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ]);
    currentWorkspaceIdState.set('ws-2');
    fixture.detectChanges();

    const mobileBtn = fixture.nativeElement.querySelector('.workspace-switcher-mobile') as HTMLButtonElement;
    expect(mobileBtn).toBeTruthy();
    expect(mobileBtn.textContent).toContain('Trabajo');
  });

  it('opens the modal when clicking the mobile button', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ]);

    const mobileBtn = fixture.nativeElement.querySelector('.workspace-switcher-mobile') as HTMLButtonElement;
    mobileBtn.click();
    fixture.detectChanges();

    const modal = fixture.nativeElement.querySelector('app-modal-shell');
    expect(modal).toBeTruthy();
  });

  it('selects a workspace from the modal and closes it', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ]);

    // Open modal
    const mobileBtn = fixture.nativeElement.querySelector('.workspace-switcher-mobile') as HTMLButtonElement;
    mobileBtn.click();
    fixture.detectChanges();

    // Click on second workspace item
    const items = document.body.querySelectorAll('.workspace-modal-item') as NodeListOf<HTMLButtonElement>;
    items[1].click();
    fixture.detectChanges();

    expect(workspaceContextMock.setCurrentWorkspaceId).toHaveBeenCalledWith('ws-2');
  });

  it('shows check icon for the active workspace in modal', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
    ]);
    currentWorkspaceIdState.set('ws-1');
    fixture.detectChanges();

    // Open modal
    const mobileBtn = fixture.nativeElement.querySelector('.workspace-switcher-mobile') as HTMLButtonElement;
    mobileBtn.click();
    fixture.detectChanges();

    const activeItem = document.body.querySelector('.workspace-modal-item--active') as HTMLElement;
    expect(activeItem).toBeTruthy();
    expect(activeItem.querySelector('.fa-check')).toBeTruthy();
  });

  it('renders all three workspaces in the mobile modal list', async () => {
    await setup([
      { id: 'ws-1', name: 'Personal' },
      { id: 'ws-2', name: 'Trabajo' },
      { id: 'ws-3', name: 'Familia' },
    ]);

    const mobileBtn = fixture.nativeElement.querySelector('.workspace-switcher-mobile') as HTMLButtonElement;
    mobileBtn.click();
    fixture.detectChanges();

    const items = document.body.querySelectorAll('.workspace-modal-item');
    expect(items.length).toBe(3);
    expect(items[2].textContent).toContain('Familia');
  });
});
