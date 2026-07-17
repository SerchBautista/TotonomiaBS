import { inject, Injectable, signal } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { catchError, map, Observable, of, tap, throwError } from 'rxjs';
import {
  LearnCatalog,
  LearnCatalogResponse,
  LearnFeatureDetail,
  LearnFeatureResponse,
  LearnTopicDetail,
  LearnTopicResponse,
} from '../../features/learn/models/learn-content.model';
import { API_SERVICE_TOKEN } from '../tokens/api-service.token';

@Injectable({
  providedIn: 'root',
})
export class LearnContentService {
  private readonly api = inject(API_SERVICE_TOKEN);
  private readonly translate = inject(TranslateService);

  private readonly catalogState = signal<LearnCatalog | null>(null);
  private readonly loadingState = signal(false);
  private readonly errorState = signal<string | null>(null);
  private readonly topicCache = new Map<string, LearnTopicDetail>();
  private readonly featureCache = new Map<string, LearnFeatureDetail>();

  readonly catalog = this.catalogState.asReadonly();
  readonly loading = this.loadingState.asReadonly();
  readonly error = this.errorState.asReadonly();

  constructor() {
    this.translate.onLangChange.subscribe(() => {
      this.catalogState.set(null);
      this.topicCache.clear();
      this.featureCache.clear();
    });
  }

  loadCatalog(): Observable<LearnCatalog> {
    this.loadingState.set(true);
    this.errorState.set(null);

    return this.api.get<LearnCatalogResponse>(`/learn?_=${Date.now()}`).pipe(
      map((response) => response.data),
      tap((catalog) => {
        this.catalogState.set(catalog);
        this.loadingState.set(false);
      }),
      catchError(() => {
        this.loadingState.set(false);
        this.errorState.set('learn.load_error');
        return throwError(() => new Error('learn.load_error'));
      })
    );
  }

  loadTopic(slug: string): Observable<LearnTopicDetail | null> {
    const cached = this.topicCache.get(slug);
    if (cached) {
      return of(cached);
    }

    return this.api.get<LearnTopicResponse>(`/learn/${slug}`).pipe(
      map((response) => response.data),
      tap((topic) => {
        this.topicCache.set(slug, topic);
      }),
      catchError(() => of(null))
    );
  }

  loadFeature(slug: string): Observable<LearnFeatureDetail | null> {
    return this.api.get<LearnFeatureResponse>(`/learn/features/${slug}?_=${Date.now()}`).pipe(
      map((response) => response.data),
      tap((feature) => {
        this.featureCache.set(slug, feature);
      }),
      catchError(() => of(null))
    );
  }

  clearCache(): void {
    this.catalogState.set(null);
    this.topicCache.clear();
    this.featureCache.clear();
  }
}
