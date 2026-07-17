import { DomPortalOutlet, TemplatePortal } from '@angular/cdk/portal';
import {
  ApplicationRef,
  ChangeDetectionStrategy,
  Component,
  effect,
  ElementRef,
  inject,
  Injector,
  input,
  OnDestroy,
  output,
  TemplateRef,
  viewChild,
  ViewContainerRef,
} from '@angular/core';

@Component({
  selector: 'app-modal-shell',
  templateUrl: './modal-shell.html',
  styleUrl: './modal-shell.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
})
export class ModalShellComponent implements OnDestroy {
  readonly open = input.required<boolean>();
  readonly title = input.required<string>();
  readonly size = input<'sm' | 'md' | 'lg'>('md');

  readonly close = output<void>();

  private readonly appRef = inject(ApplicationRef);
  private readonly injector = inject(Injector);
  private readonly viewContainerRef = inject(ViewContainerRef);

  private readonly portalTemplate = viewChild.required<TemplateRef<unknown>>('modalTpl');
  private readonly panel = viewChild<ElementRef<HTMLElement>>('panel');

  private portalOutlet: DomPortalOutlet | null = null;
  private portalHost: HTMLDivElement | null = null;
  private attachedPortal: TemplatePortal<unknown> | null = null;

  constructor() {
    effect(() => {
      if (this.open()) {
        this.attachPortal();
        queueMicrotask(() => this.panel()?.nativeElement.focus());
      } else {
        this.detachPortal();
      }
    });
  }

  ngOnDestroy(): void {
    this.detachPortal();
  }

  onBackdropClick(event: MouseEvent): void {
    if (event.target === event.currentTarget) {
      this.onClose();
    }
  }

  onClose(): void {
    this.close.emit();
  }

  onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      this.onClose();
    }
  }

  private attachPortal(): void {
    if (this.attachedPortal?.isAttached) {
      return;
    }

    if (!this.portalHost) {
      this.portalHost = document.createElement('div');
      this.portalHost.classList.add('modal-shell-portal-host');
      this.portalHost.style.display = 'contents';
      document.body.appendChild(this.portalHost);
    }

    if (!this.portalOutlet) {
      this.portalOutlet = new DomPortalOutlet(this.portalHost, this.appRef, this.injector);
    }

    const portal = new TemplatePortal(this.portalTemplate(), this.viewContainerRef);
    portal.attach(this.portalOutlet);
    this.attachedPortal = portal;
  }

  private detachPortal(): void {
    this.attachedPortal?.detach();
    this.attachedPortal = null;
    this.portalOutlet?.dispose();
    this.portalOutlet = null;
    this.portalHost = null;
  }
}
