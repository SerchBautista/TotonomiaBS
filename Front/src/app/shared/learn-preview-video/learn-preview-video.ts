import {
  AfterViewInit,
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  ViewChild,
  computed,
  effect,
  input,
} from '@angular/core';

@Component({
  selector: 'app-learn-preview-video',
  templateUrl: './learn-preview-video.html',
  styleUrl: './learn-preview-video.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LearnPreviewVideoComponent implements AfterViewInit {
  readonly title = input.required<string>();
  readonly poster = input('');
  readonly webm = input('');
  readonly mp4 = input('');
  readonly cacheVersion = input('1');

  @ViewChild('player') private playerRef?: ElementRef<HTMLVideoElement>;

  /** MP4 first — best compatibility with Chrome and Safari. */
  readonly videoSrc = computed(() => {
    const mp4 = this.mp4()?.trim();
    const webm = this.webm()?.trim();
    const src = mp4 || webm;
    if (!src) {
      return '';
    }
    const version = this.cacheVersion();
    const separator = src.includes('?') ? '&' : '?';
    return `${src}${separator}v=${encodeURIComponent(version)}`;
  });

  readonly posterSrc = computed(() => {
    const poster = this.poster()?.trim();
    if (!poster) {
      return null;
    }
    const version = this.cacheVersion();
    const separator = poster.includes('?') ? '&' : '?';
    return `${poster}${separator}v=${encodeURIComponent(version)}`;
  });

  constructor() {
    effect(() => {
      this.videoSrc();
      queueMicrotask(() => void this.startPlayback());
    });
  }

  ngAfterViewInit(): void {
    void this.startPlayback();
  }

  onMediaReady(): void {
    void this.startPlayback();
  }

  private async startPlayback(): Promise<void> {
    const player = this.playerRef?.nativeElement;
    if (!player || !this.videoSrc()) {
      return;
    }
    player.defaultMuted = true;
    player.muted = true;
    try {
      await player.play();
    } catch {
      // Autoplay may be deferred until the browser finishes loading `src`.
    }
  }
}
