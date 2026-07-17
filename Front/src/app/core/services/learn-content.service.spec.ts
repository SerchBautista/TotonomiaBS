import { TestBed } from '@angular/core/testing';
import { provideTranslateService } from '@ngx-translate/core';
import { of, throwError } from 'rxjs';
import { describe, expect, it, vi } from 'vitest';
import { API_SERVICE_TOKEN } from '../tokens/api-service.token';
import { LearnContentService } from './learn-content.service';

describe('LearnContentService', () => {
  it('loads catalog from the learn API', () => {
    const get = vi.fn(() =>
      of({
        data: {
          version: 1,
          updatedAt: '2026-06-06T00:00:00Z',
          locale: 'es',
          hub: {
            eyebrow: 'Educación financiera',
            title: 'Mejora tus finanzas personales',
            lead: 'Lead',
            topicsTitle: 'Explora estos temas',
          },
          cta: {
            title: 'CTA',
            subtitle: 'Sub',
            primary: 'Crear cuenta',
            secondary: 'Ver precios',
          },
          topics: [],
        },
      })
    );

    TestBed.configureTestingModule({
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        LearnContentService,
        {
          provide: API_SERVICE_TOKEN,
          useValue: { get },
        },
      ],
    });

    const service = TestBed.inject(LearnContentService);

    service.loadCatalog().subscribe((catalog) => {
      expect(get).toHaveBeenCalledWith('/learn');
      expect(catalog.hub.title).toBe('Mejora tus finanzas personales');
    });
  });

  it('sets error state when catalog request fails', () => {
    const get = vi.fn(() => throwError(() => new Error('network')));

    TestBed.configureTestingModule({
      providers: [
        provideTranslateService({ fallbackLang: 'es', lang: 'es' }),
        LearnContentService,
        {
          provide: API_SERVICE_TOKEN,
          useValue: { get },
        },
      ],
    });

    const service = TestBed.inject(LearnContentService);

    service.loadCatalog().subscribe({
      error: () => {
        expect(service.error()).toBe('learn.load_error');
      },
    });
  });
});
