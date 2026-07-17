import React from 'react';
import { AbsoluteFill, Img, interpolate, staticFile, useCurrentFrame } from 'remotion';
import type { WalkthroughSlide } from './types';

interface ScreenSlideProps {
  slide: WalkthroughSlide;
  durationInFrames: number;
}

const FADE_FRAMES = 8;
const ENTER_FRAMES = 10;

export const ScreenSlide: React.FC<ScreenSlideProps> = ({ slide, durationInFrames }) => {
  const frame = useCurrentFrame();

  const fadeIn = interpolate(frame, [0, FADE_FRAMES], [0, 1], { extrapolateRight: 'clamp' });
  const fadeOut = interpolate(frame, [durationInFrames - FADE_FRAMES, durationInFrames], [1, 0], {
    extrapolateLeft: 'clamp',
  });
  const opacity = Math.min(fadeIn, fadeOut);

  const enter = interpolate(frame, [0, ENTER_FRAMES], [0, 1], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });
  const translateY = (1 - enter) * 20;
  const scale = 0.97 + enter * 0.03;

  return (
    <AbsoluteFill style={{ backgroundColor: '#0b1220' }}>
      <AbsoluteFill
        style={{
          opacity,
          padding: 32,
          transform: `translateY(${translateY}px) scale(${scale})`,
        }}
      >
        <Img
          src={staticFile(`screens/${slide.file}`)}
          style={{
            width: '100%',
            height: '100%',
            objectFit: 'contain',
            objectPosition: 'top center',
          }}
        />
      </AbsoluteFill>

      <AbsoluteFill
        style={{
          justifyContent: 'flex-end',
          padding: '40px 56px',
          background: 'linear-gradient(transparent 62%, rgba(8, 11, 20, 0.92))',
          pointerEvents: 'none',
        }}
      >
        <p
          style={{
            color: '#f8fafc',
            fontFamily: 'Inter, system-ui, sans-serif',
            fontSize: 36,
            fontWeight: 700,
            letterSpacing: '-0.02em',
            margin: 0,
            maxWidth: 900,
            opacity,
            transform: `translateY(${(1 - enter) * 12}px)`,
          }}
        >
          {slide.caption}
        </p>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
