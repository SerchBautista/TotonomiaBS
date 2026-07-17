import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideTranslateService } from '@ngx-translate/core';
import { of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { LearnContentService } from '../../../core/services/learn-content.service';
import { LearnHubComponent } from './learn-hub';

const mockCatalog = {
  version: 1,
  updatedAt: '2026-06-06T00:00:00Z',
  locale: 'es',
  hub: {
    eyebrow: 'Educación financiera',
    title: 'Mejora tus finanzas',
    lead: 'Aprende a gestionar tu dinero',
    topicsTitle: 'Explora estos temas',
    overviewTitle: 'Totonomía en acción',
    overviewLead: 'Un vistazo rápido al producto.',
    featuresTitle: 'Cómo funciona Totonomía',
    featuresLead: 'Explora cada área sin salir de esta página.',
    overviewMedia: {
      poster: '/media/walkthroughs/posters/overview.jpg',
      webm: '/media/walkthroughs/overview.webm',
      mp4: '/media/walkthroughs/overview.mp4',
    },
  },
  cta: {
    title: 'Empieza hoy',
    subtitle: 'Crea tu cuenta gratis',
    primary: 'Registrarse',
    secondary: 'Ver precios',
  },
  topics: [
    {
      id: 'budgeting',
      slug: 'budgeting',
      icon: { web: 'fa-chart-pie', mobile: 'pie_chart' },
      title: 'Presupuestos',
      summary: 'Aprende a presupuestar',
      eyebrow: 'Planificación',
    },
  ],
  features: [
    {
      id: 'dashboard',
      slug: 'dashboard',
      icon: { web: 'fa-chart-line', mobile: 'insights' },
      title: 'Panel financiero',
      summary: 'Resumen dashboard',
      eyebrow: 'Dashboard',
      lead: 'Tu centro de control.',
      screenshot: '/media/walkthroughs/screens/dashboard.png',
      sections: [{ title: 'Presupuesto', body: 'Control mensual.' }],
    },
  ],
};

describe('LearnHubComponent', () => {
  let fixture: ComponentFixture<LearnHubComponent>;
  const loadingState = signal(false);
  const errorState = signal<string | null>(null);
  const loadCatalog = vi.fn(() => of(mockCatalog));

  beforeEach(async () => {
    loadingState.set(false);
    errorState.set(null);
    loadCatalog.mockReturnValue(of(mockCatalog));

    await TestBed.configureTestingModule({
      imports: [LearnHubComponent],
      providers: [
        provideRouter([]),
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        {
          provide: LearnContentService,
          useValue: {
            loading: loadingState.asReadonly(),
            error: errorState.asReadonly(),
            loadCatalog,
          },
        },
      ],
    }).compileComponents();
  });

  it('loads and renders catalog content when available', async () => {
    fixture = TestBed.createComponent(LearnHubComponent);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    const text: string = fixture.nativeElement.textContent ?? '';
    expect(text).toContain('Mejora tus finanzas');
    expect(text).toContain('Presupuestos');
    expect(text).toContain('Totonomía en acción');
    expect(text).toContain('Cómo funciona Totonomía');
    expect(text).toContain('Panel financiero');
    expect(fixture.nativeElement.querySelector('video')).toBeTruthy();
    expect(fixture.nativeElement.querySelector('.learn-showcase')).toBeTruthy();
    expect(loadCatalog).toHaveBeenCalled();
  });

  it('renders loading state when catalog is loading', () => {
    loadingState.set(true);
    fixture = TestBed.createComponent(LearnHubComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('learn.loading');
  });

  it('renders error state when catalog loading fails', () => {
    errorState.set('learn.load_error');
    fixture = TestBed.createComponent(LearnHubComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('learn.load_error');
  });
});
