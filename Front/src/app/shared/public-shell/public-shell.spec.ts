import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { AUTH_STATE_TOKEN } from '../../core/tokens/auth-state.token';
import { PublicShellComponent } from './public-shell';

describe('PublicShellComponent', () => {
  let fixture: ComponentFixture<PublicShellComponent>;
  const authMock = {
    isLoggedIn: vi.fn().mockReturnValue(false),
    token: vi.fn(),
    role: vi.fn(),
    emailVerified: vi.fn(),
    userId: vi.fn(),
    defaultWorkspaceId: vi.fn(),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PublicShellComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        { provide: AUTH_STATE_TOKEN, useValue: authMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PublicShellComponent);
    fixture.detectChanges();
  });

  it('renders the public shell with language switcher', () => {
    expect(fixture.nativeElement.querySelector('app-language-switcher')).toBeTruthy();
  });

  it('applies landing variant class when configured', () => {
    fixture = TestBed.createComponent(PublicShellComponent);
    fixture.componentInstance.variant = 'landing';
    fixture.detectChanges();

    const header = fixture.nativeElement.querySelector('.public-shell') as HTMLElement;
    expect(header.classList.contains('public-shell--landing')).toBe(true);
  });
});
