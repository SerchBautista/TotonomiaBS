import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { Workspace } from '../../core/models/workspace.model';

@Component({
  selector: 'app-workspace-selector-list',
  standalone: true,
  templateUrl: './workspace-selector-list.html',
  styleUrl: './workspace-selector-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WorkspaceSelectorListComponent {
  readonly workspaces = input<readonly Workspace[]>([]);
  readonly selectedIds = input<readonly string[]>([]);
  readonly legend = input<string>('');
  readonly hint = input<string>('');
  readonly disabled = input<boolean>(false);

  readonly selectedIdSet = computed(() => new Set(this.selectedIds()));
  readonly selectedIdsChange = output<string[]>();

  toggle(workspaceId: string, checked: boolean): void {
    const next = new Set(this.selectedIds());

    if (checked) {
      next.add(workspaceId);
    } else {
      next.delete(workspaceId);
    }

    this.selectedIdsChange.emit([...next]);
  }
}
