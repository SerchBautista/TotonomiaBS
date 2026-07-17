import { ChangeDetectionStrategy, Component, OnInit, inject, signal } from '@angular/core';
import {
  AbstractControl,
  FormBuilder,
  ReactiveFormsModule,
  ValidationErrors,
  Validators,
} from '@angular/forms';
import { TranslateModule } from '@ngx-translate/core';
import {
  AdminAdministratorsService,
  AdministratorItem,
} from '../../../../core/services/admin-administrators';
import { CrudFormComponent } from '../../../../shared/crud-form/crud-form';
import { CrudFormFacade } from '../../../../core/crud/crud-form-facade';
import { FormCardComponent } from '../../../../shared/form-card/form-card';

function passwordMatchValidator(control: AbstractControl): ValidationErrors | null {
  const password = control.get('password')?.value as string | null;
  const confirmation = control.get('password_confirmation')?.value as string | null;

  if (!password && !confirmation) {
    return null;
  }

  return password === confirmation ? null : { passwordMismatch: true };
}

@Component({
  selector: 'app-administrator-form',
  imports: [ReactiveFormsModule, TranslateModule, CrudFormComponent, FormCardComponent],
  templateUrl: './administrator-form.html',
  styleUrl: './administrator-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdministratorFormComponent
  extends CrudFormFacade<
    AdministratorItem,
    {
      name: string;
      email: string;
      password: string | null;
      password_confirmation: string | null;
      roles: string[];
      permissions: string[];
    },
    {
      name: string;
      email: string;
      password: string | null;
      password_confirmation: string | null;
      roles: string[];
      permissions: string[];
    }
  >
  implements OnInit
{
  private readonly fb = inject(FormBuilder);
  private readonly administratorsService = inject(AdminAdministratorsService);

  readonly entityKey = 'administrators';
  readonly availableRoles = signal<string[]>([]);
  readonly availablePermissions = signal<string[]>([]);

  protected readonly crudService = this.administratorsService;
  protected readonly loadErrorKey = 'administrators.load_error';
  protected readonly saveErrorKey = 'administrators.save_error';
  protected readonly successRoute = '/admin/administrators';

  protected readonly form = this.fb.group(
    {
      name: ['', [Validators.required, Validators.maxLength(120)]],
      email: ['', [Validators.required, Validators.email, Validators.maxLength(190)]],
      password: [''],
      password_confirmation: [''],
      roles: [['admin' as string], [Validators.required]],
      permissions: [[] as string[]],
    },
    { validators: passwordMatchValidator },
  );

  ngOnInit(): void {
    this.initCrudForm();
    this.setupModeValidators();
    this.loadOptions();
  }

  submit(): void {
    if (!this.hasAdminRole()) {
      this.toastService.error(this.translate.instant('administrators.admin_role_required'));
      return;
    }

    this.submitCrud();
  }

  toggleRole(role: string, checked: boolean): void {
    const current = this.form.controls.roles.value ?? [];
    const next = checked
      ? Array.from(new Set([...current, role]))
      : current.filter((item) => item !== role);

    if (!next.includes('admin')) {
      this.toastService.error(this.translate.instant('administrators.admin_role_required'));
      return;
    }

    this.form.controls.roles.setValue(next);
    this.form.controls.roles.markAsDirty();
    this.form.controls.roles.updateValueAndValidity();
  }

  togglePermission(permission: string, checked: boolean): void {
    const current = this.form.controls.permissions.value ?? [];
    const next = checked
      ? Array.from(new Set([...current, permission]))
      : current.filter((item) => item !== permission);

    this.form.controls.permissions.setValue(next);
    this.form.controls.permissions.markAsDirty();
    this.form.controls.permissions.updateValueAndValidity();
  }

  hasRole(role: string): boolean {
    return (this.form.controls.roles.value ?? []).includes(role);
  }

  hasPermission(permission: string): boolean {
    return (this.form.controls.permissions.value ?? []).includes(permission);
  }

  protected mapItemToForm(item: AdministratorItem): void {
    this.form.patchValue({
      name: item.name,
      email: item.email,
      password: '',
      password_confirmation: '',
      roles: item.roles,
      permissions: item.direct_permissions,
    });
  }

  protected buildCreatePayload(): {
    name: string;
    email: string;
    password: string | null;
    password_confirmation: string | null;
    roles: string[];
    permissions: string[];
  } {
    return this.buildPayload();
  }

  protected buildUpdatePayload(): {
    name: string;
    email: string;
    password: string | null;
    password_confirmation: string | null;
    roles: string[];
    permissions: string[];
  } {
    return this.buildPayload();
  }

  private loadOptions(): void {
    this.administratorsService.options().subscribe({
      next: (response) => {
        this.availableRoles.set(response.data.roles);
        this.availablePermissions.set(response.data.permissions);

        if (!this.hasAdminRole()) {
          this.form.controls.roles.setValue(['admin']);
        }
      },
      error: () => {},
    });
  }

  private setupModeValidators(): void {
    if (this.mode === 'create') {
      this.form.controls.password.setValidators([Validators.required, Validators.minLength(8)]);
      this.form.controls.password_confirmation.setValidators([Validators.required]);
    } else {
      this.form.controls.password.setValidators([Validators.minLength(8)]);
      this.form.controls.password_confirmation.setValidators([]);
    }

    this.form.controls.password.updateValueAndValidity();
    this.form.controls.password_confirmation.updateValueAndValidity();
  }

  private hasAdminRole(): boolean {
    return (this.form.controls.roles.value ?? []).includes('admin');
  }

  private buildPayload(): {
    name: string;
    email: string;
    password: string | null;
    password_confirmation: string | null;
    roles: string[];
    permissions: string[];
  } {
    const passwordRaw = this.form.value.password ?? '';
    const confirmationRaw = this.form.value.password_confirmation ?? '';
    const password = passwordRaw.trim() ? passwordRaw : null;
    const passwordConfirmation = confirmationRaw.trim() ? confirmationRaw : null;

    return {
      name: (this.form.value.name ?? '').trim(),
      email: (this.form.value.email ?? '').trim(),
      password,
      password_confirmation: passwordConfirmation,
      roles: this.form.value.roles ?? [],
      permissions: this.form.value.permissions ?? [],
    };
  }
}
