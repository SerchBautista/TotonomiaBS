import { ChangeDetectionStrategy, Component, computed, HostListener, input, signal } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { TranslateModule } from '@ngx-translate/core';
import { LearnFeatureShowcase } from '../../features/learn/models/learn-content.model';

const MIN_ZOOM = 1;
const MAX_ZOOM = 2.5;
const ZOOM_STEP = 0.25;

@Component({
  selector: 'app-learn-feature-showcase',
  imports: [DecimalPipe, TranslateModule],
  templateUrl: './learn-feature-showcase.html',
  styleUrl: './learn-feature-showcase.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LearnFeatureShowcaseComponent {
  readonly features = input.required<LearnFeatureShowcase[]>();
  readonly cacheVersion = input<string>('');

  readonly activeIndex = signal(0);
  readonly zoomOpen = signal(false);
  readonly zoomLevel = signal(1);

  readonly activeFeature = computed(() => {
    const items = this.features();
    const index = this.activeIndex();
    return items[index] ?? items[0] ?? null;
  });

  readonly canZoomIn = computed(() => this.zoomLevel() < MAX_ZOOM);
  readonly canZoomOut = computed(() => this.zoomLevel() > MIN_ZOOM);

  @HostListener('document:keydown', ['$event'])
  onDocumentKeydown(event: KeyboardEvent): void {
    if (!this.zoomOpen()) {
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      this.closeZoom();
      return;
    }

    if (event.key === '+' || event.key === '=') {
      event.preventDefault();
      this.zoomIn();
      return;
    }

    if (event.key === '-') {
      event.preventDefault();
      this.zoomOut();
    }
  }

  selectTab(index: number): void {
    if (index < 0 || index >= this.features().length) {
      return;
    }
    this.closeZoom();
    this.activeIndex.set(index);
  }

  openZoom(): void {
    this.zoomLevel.set(1);
    this.zoomOpen.set(true);
  }

  closeZoom(): void {
    this.zoomOpen.set(false);
    this.zoomLevel.set(1);
  }

  zoomIn(): void {
    if (!this.canZoomIn()) {
      return;
    }
    this.zoomLevel.update((level) => Math.min(MAX_ZOOM, Number((level + ZOOM_STEP).toFixed(2))));
  }

  zoomOut(): void {
    if (!this.canZoomOut()) {
      return;
    }
    this.zoomLevel.update((level) => Math.max(MIN_ZOOM, Number((level - ZOOM_STEP).toFixed(2))));
  }

  screenshotUrl(path: string): string {
    const version = this.cacheVersion();
    if (!path) {
      return '';
    }
    return version ? `${path}?v=${encodeURIComponent(version)}` : path;
  }

  tabId(index: number): string {
    return `learn-showcase-tab-${index}`;
  }

  panelId(index: number): string {
    return `learn-showcase-panel-${index}`;
  }
}
