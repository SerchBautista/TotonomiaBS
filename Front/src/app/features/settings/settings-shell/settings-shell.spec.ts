import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter, Router, Routes } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { Component } from '@angular/core';
import { Location } from '@angular/common';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SettingsShellComponent } from './settings-shell';

@Component({ selector: 'app-test-stub', standalone: true, template: '<p>child</p>' })
class TestStubComponent {}

const routes: Routes = [
  {
    path: 'user/settings',
    component: SettingsShellComponent,
    children: [{ path: 'categories', component: TestStubComponent }],
  },
  { path: '**', redirectTo: 'user/settings' },
];

describe('SettingsShellComponent', () => {
  let fixture: ComponentFixture<SettingsShellComponent>;
  let component: SettingsShellComponent;
  let router: Router;
  let location: Location;

  beforeEach(async () => {
    vi.clearAllMocks();

    TestBed.configureTestingModule({
      imports: [SettingsShellComponent, TranslateModule.forRoot()],
      providers: [provideRouter(routes)],
    });

    router = TestBed.inject(Router);
    location = TestBed.inject(Location);
    await router.navigate(['/user/settings']);
  });

  it('should create the component', () => {
    fixture = TestBed.createComponent(SettingsShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    expect(component).toBeTruthy();
  });

  it('should expose the overview state when route is /user/settings', () => {
    fixture = TestBed.createComponent(SettingsShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    expect(component.isOverview()).toBe(true);
  });

  it('should render the settings modules overview', async () => {
    fixture = TestBed.createComponent(SettingsShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    const items = fixture.nativeElement.querySelectorAll('.settings-menu__item');
    expect(items.length).toBe(component.modules.length);
  });

  it('should navigate to a child route when a module is clicked', async () => {
    fixture = TestBed.createComponent(SettingsShellComponent);
    component = fixture.componentInstance;
    const navigateSpy = vi.spyOn(router, 'navigate');

    fixture.detectChanges();
    component.navigateTo('categories');
    expect(navigateSpy).toHaveBeenCalledWith(['/user/settings', 'categories']);
  });

  it('should not expose an active module when on the overview route', () => {
    fixture = TestBed.createComponent(SettingsShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    expect(component.activeModule()).toBeNull();
  });

  it('should expose an active module when on a child route', async () => {
    await router.navigate(['/user/settings/categories']);
    fixture = TestBed.createComponent(SettingsShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    expect(component.isOverview()).toBe(false);
    expect(component.activeModule()?.id).toBe('categories');
  });
});
