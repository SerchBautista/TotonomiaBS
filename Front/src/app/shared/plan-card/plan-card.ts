import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';

export interface PlanInfo {
  id: 'free' | 'premium';
  name: string;
  price: string;
  features: string[];
}

@Component({
  selector: 'app-plan-card',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './plan-card.html',
  styleUrl: './plan-card.scss',
})
export class PlanCardComponent {
  @Input() plan!: PlanInfo;
  @Input() current = false;
  @Input() loading = false;
  @Output() upgrade = new EventEmitter<void>();
}
