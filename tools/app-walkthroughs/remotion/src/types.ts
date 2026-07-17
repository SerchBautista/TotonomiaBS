export interface SlideZoom {
  scale: number;
  x: number;
  y: number;
}

export interface WalkthroughSlide {
  file: string;
  caption: string;
  durationFrames: number;
  zoom?: SlideZoom;
}

export interface WalkthroughManifest {
  id: string;
  compositionId: string;
  title: string;
  fps: number;
  width: number;
  height: number;
  slides: WalkthroughSlide[];
}
