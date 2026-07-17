import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { combineLatest, merge, of } from 'rxjs';
import { map, startWith, switchMap } from 'rxjs/operators';
import { ContentHeroComponent } from '../../../shared/content-hero/content-hero';
import { CtaBannerComponent } from '../../../shared/cta-banner/cta-banner';
import { LearnContentService } from '../../../core/services/learn-content.service';

@Component({
  selector: 'app-learn-feature',
  imports: [RouterLink, TranslateModule, ContentHeroComponent, CtaBannerComponent],
  templateUrl: './learn-feature.html',
  styleUrl: './learn-feature.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LearnFeatureComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly learnContent = inject(LearnContentService);
  private readonly translate = inject(TranslateService);

  readonly catalog = toSignal(
    merge(of(void 0), this.translate.onLangChange.pipe(map(() => void 0))).pipe(
      switchMap(() => this.learnContent.loadCatalog())
    ),
    { initialValue: null }
  );

  readonly feature = toSignal(
    combineLatest([
      this.route.paramMap.pipe(map((params) => params.get('slug') ?? '')),
      this.translate.onLangChange.pipe(startWith(null)),
    ]).pipe(
      switchMap(([slug]) => (slug ? this.learnContent.loadFeature(slug) : of(null)))
    ),
    { initialValue: null }
  );
}
