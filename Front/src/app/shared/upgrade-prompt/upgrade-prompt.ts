import { Component, EventEmitter, inject, Input, Output } from '@angular/core';
import { Router } from '@angular/router';

@Component({
  selector: 'app-upgrade-prompt',
  templateUrl: './upgrade-prompt.html',
  styleUrl: './upgrade-prompt.scss',
  standalone: true,
})
export class UpgradePromptComponent {
  private readonly router = inject(Router);

  @Input() title = '';
  @Input() benefits: string[] = [];
  @Output() upgradeClicked = new EventEmitter<void>();

  onUpgradeClick(): void {
    this.upgradeClicked.emit();
    this.router.navigate(['/pricing']);
  }
}
