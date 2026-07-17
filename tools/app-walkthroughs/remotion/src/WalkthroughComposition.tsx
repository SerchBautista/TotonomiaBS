import React from 'react';
import { AbsoluteFill, Sequence } from 'remotion';
import { dashboardManifest, budgetsManifest, expensesManifest, fixedExpensesManifest, overviewManifest, workspacesManifest } from './manifest';
import { ScreenSlide } from './ScreenSlide';
import type { WalkthroughManifest } from './types';

interface WalkthroughCompositionProps {
  manifest: WalkthroughManifest;
}

export const WalkthroughComposition: React.FC<WalkthroughCompositionProps> = ({ manifest }) => {
  let from = 0;

  return (
    <AbsoluteFill>
      {manifest.slides.map((slide) => {
        const sequence = (
          <Sequence key={slide.file} from={from} durationInFrames={slide.durationFrames}>
            <ScreenSlide slide={slide} durationInFrames={slide.durationFrames} />
          </Sequence>
        );
        from += slide.durationFrames;
        return sequence;
      })}
    </AbsoluteFill>
  );
};

export const DashboardWalkthrough: React.FC = () => (
  <WalkthroughComposition manifest={dashboardManifest} />
);

export const OverviewWalkthrough: React.FC = () => (
  <WalkthroughComposition manifest={overviewManifest} />
);

export const ExpensesWalkthrough: React.FC = () => (
  <WalkthroughComposition manifest={expensesManifest} />
);

export const BudgetsWalkthrough: React.FC = () => (
  <WalkthroughComposition manifest={budgetsManifest} />
);

export const FixedExpensesWalkthrough: React.FC = () => (
  <WalkthroughComposition manifest={fixedExpensesManifest} />
);

export const WorkspacesWalkthrough: React.FC = () => (
  <WalkthroughComposition manifest={workspacesManifest} />
);
