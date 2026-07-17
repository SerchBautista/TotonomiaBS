import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { beforeEach, describe, expect, it } from 'vitest';
import { LearnFeatureShowcaseComponent } from './learn-feature-showcase';
import { LearnFeatureShowcase as LearnFeatureShowcaseModel } from '../../features/learn/models/learn-content.model';

const mockFeatures: LearnFeatureShowcaseModel[] = [
  {
    id: 'dashboard',
    slug: 'dashboard',
    icon: { web: 'fa-chart-line', mobile: 'insights' },
    title: 'Panel financiero',
    summary: 'Resumen',
    eyebrow: 'Dashboard',
    lead: 'Tu centro de control financiero.',
    screenshot: '/media/walkthroughs/screens/dashboard.png',
    sections: [
      { title: 'Estado del presupuesto', body: 'Consulta cuánto llevas consumido.' },
    ],
  },
  {
    id: 'expenses',
    slug: 'expenses',
    icon: { web: 'fa-clipboard-list', mobile: 'receipt_long' },
    title: 'Registro de gastos',
    summary: 'Resumen',
    eyebrow: 'Gastos',
    lead: 'Cada transacción cuenta.',
    screenshot: '/media/walkthroughs/screens/expenses.png',
    sections: [{ title: 'Listado', body: 'Consulta todos tus movimientos.' }],
  },
];

describe('LearnFeatureShowcaseComponent', () => {
  let fixture: ComponentFixture<LearnFeatureShowcaseComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LearnFeatureShowcaseComponent],
      providers: [provideTranslateService({ fallbackLang: 'es', lang: 'es' })],
    }).compileComponents();

    fixture = TestBed.createComponent(LearnFeatureShowcaseComponent);
    fixture.componentRef.setInput('features', mockFeatures);
    fixture.componentRef.setInput('cacheVersion', '2026-06-27');
    fixture.detectChanges();
  });

  it('renders the first feature by default', () => {
    const text: string = fixture.nativeElement.textContent ?? '';
    expect(text).toContain('Panel financiero');
    expect(text).toContain('Estado del presupuesto');
    expect(fixture.nativeElement.querySelector('img')?.getAttribute('src')).toContain('dashboard.png');
  });

  it('switches content when another tab is selected', () => {
    const tabs = fixture.nativeElement.querySelectorAll('.learn-showcase__tab');
    (tabs[1] as HTMLButtonElement).click();
    fixture.detectChanges();

    const text: string = fixture.nativeElement.textContent ?? '';
    expect(text).toContain('Registro de gastos');
    expect(text).toContain('Listado');
  });

  it('opens and closes the zoom lightbox', () => {
    const trigger = fixture.nativeElement.querySelector('.learn-showcase__media-trigger') as HTMLButtonElement;
    trigger.click();
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('.learn-showcase__lightbox')).toBeTruthy();

    const closeButton = fixture.nativeElement.querySelector(
      '.learn-showcase__lightbox-btn--close',
    ) as HTMLButtonElement;
    closeButton.click();
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('.learn-showcase__lightbox')).toBeFalsy();
  });

  it('increases zoom level from the lightbox controls', () => {
    const trigger = fixture.nativeElement.querySelector('.learn-showcase__media-trigger') as HTMLButtonElement;
    trigger.click();
    fixture.detectChanges();

    const zoomIn = fixture.nativeElement.querySelectorAll(
      '.learn-showcase__lightbox-actions .learn-showcase__lightbox-btn',
    )[1] as HTMLButtonElement;
    zoomIn.click();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('125%');
  });
});
