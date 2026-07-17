import { ComponentFixture, TestBed } from '@angular/core/testing';
import { TranslateService } from '@ngx-translate/core';
import { provideTranslateService } from '@ngx-translate/core';
import { LanguageSwitcherComponent } from './language-switcher';

describe('LanguageSwitcherComponent', () => {
  let fixture: ComponentFixture<LanguageSwitcherComponent>;
  let component: LanguageSwitcherComponent;
  let translate: TranslateService;

  beforeEach(async () => {
    localStorage.removeItem('app_lang');

    await TestBed.configureTestingModule({
      imports: [LanguageSwitcherComponent],
      providers: [provideTranslateService({ fallbackLang: 'es', lang: 'es' })],
    }).compileComponents();

    translate = TestBed.inject(TranslateService);
  });

  afterEach(() => {
    localStorage.removeItem('app_lang');
  });

  it('persists selected language to localStorage and updates TranslateService', () => {
    fixture = TestBed.createComponent(LanguageSwitcherComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const useSpy = vi.spyOn(translate, 'use');

    component.useLanguage('en');

    expect(component.language).toBe('en');
    expect(localStorage.getItem('app_lang')).toBe('en');
    expect(useSpy).toHaveBeenCalledWith('en');
  });

  it('marks the active language button in the template', () => {
    localStorage.setItem('app_lang', 'en');
    fixture = TestBed.createComponent(LanguageSwitcherComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const buttons: HTMLButtonElement[] = Array.from(
      fixture.nativeElement.querySelectorAll('button'),
    );
    const enBtn = buttons.find((btn) => btn.textContent?.trim() === 'EN');
    const esBtn = buttons.find((btn) => btn.textContent?.trim() === 'ES');

    expect(enBtn?.classList.contains('active')).toBe(true);
    expect(esBtn?.classList.contains('active')).toBe(false);
  });

  it('initializes from localStorage when app_lang is set', async () => {
    localStorage.setItem('app_lang', 'en');

    const freshFixture = TestBed.createComponent(LanguageSwitcherComponent);
    expect(freshFixture.componentInstance.language).toBe('en');
  });
});
