import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { merge, of } from 'rxjs';
import { map, switchMap } from 'rxjs/operators';
import { ContentHeroComponent } from '../../../shared/content-hero/content-hero';
import { TopicCardComponent } from '../../../shared/topic-card/topic-card';
import { CtaBannerComponent } from '../../../shared/cta-banner/cta-banner';
import { LearnFeatureShowcaseComponent } from '../../../shared/learn-feature-showcase/learn-feature-showcase';
import { LearnPreviewVideoComponent } from '../../../shared/learn-preview-video/learn-preview-video';
import { LearnContentService } from '../../../core/services/learn-content.service';

@Component({
  selector: 'app-learn-hub',
  imports: [
    TranslateModule,
    ContentHeroComponent,
    TopicCardComponent,
    CtaBannerComponent,
    LearnPreviewVideoComponent,
    LearnFeatureShowcaseComponent,
  ],
  templateUrl: './learn-hub.html',
  styleUrl: './learn-hub.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LearnHubComponent {
  private readonly learnContent = inject(LearnContentService);
  private readonly translate = inject(TranslateService);

  readonly loading = this.learnContent.loading;
  readonly error = this.learnContent.error;

  readonly catalog = toSignal(
    merge(of(void 0), this.translate.onLangChange.pipe(map(() => void 0))).pipe(
      switchMap(() => this.learnContent.loadCatalog())
    ),
    { initialValue: null }
  );
}
