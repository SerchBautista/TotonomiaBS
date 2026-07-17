import React from 'react';
import { Composition } from 'remotion';
import {
  BudgetsWalkthrough,
  DashboardWalkthrough,
  ExpensesWalkthrough,
  FixedExpensesWalkthrough,
  OverviewWalkthrough,
  WorkspacesWalkthrough,
} from './WalkthroughComposition';
import {
  budgetsManifest,
  dashboardManifest,
  expensesManifest,
  fixedExpensesManifest,
  overviewManifest,
  workspacesManifest,
  totalDurationFrames,
} from './manifest';

export const RemotionRoot: React.FC = () => {
  return (
    <>
      <Composition
        id={dashboardManifest.compositionId}
        component={DashboardWalkthrough}
        durationInFrames={totalDurationFrames(dashboardManifest)}
        fps={dashboardManifest.fps}
        width={dashboardManifest.width}
        height={dashboardManifest.height}
      />
      <Composition
        id={overviewManifest.compositionId}
        component={OverviewWalkthrough}
        durationInFrames={totalDurationFrames(overviewManifest)}
        fps={overviewManifest.fps}
        width={overviewManifest.width}
        height={overviewManifest.height}
      />
      <Composition
        id={expensesManifest.compositionId}
        component={ExpensesWalkthrough}
        durationInFrames={totalDurationFrames(expensesManifest)}
        fps={expensesManifest.fps}
        width={expensesManifest.width}
        height={expensesManifest.height}
      />
      <Composition
        id={budgetsManifest.compositionId}
        component={BudgetsWalkthrough}
        durationInFrames={totalDurationFrames(budgetsManifest)}
        fps={budgetsManifest.fps}
        width={budgetsManifest.width}
        height={budgetsManifest.height}
      />
      <Composition
        id={fixedExpensesManifest.compositionId}
        component={FixedExpensesWalkthrough}
        durationInFrames={totalDurationFrames(fixedExpensesManifest)}
        fps={fixedExpensesManifest.fps}
        width={fixedExpensesManifest.width}
        height={fixedExpensesManifest.height}
      />
      <Composition
        id={workspacesManifest.compositionId}
        component={WorkspacesWalkthrough}
        durationInFrames={totalDurationFrames(workspacesManifest)}
        fps={workspacesManifest.fps}
        width={workspacesManifest.width}
        height={workspacesManifest.height}
      />
    </>
  );
};
