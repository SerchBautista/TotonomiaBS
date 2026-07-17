import { FormArray, FormControl, FormGroup, Validators } from '@angular/forms';
import { BACKEND_ERROR_CODES } from './backend-error-codes';
import {
  applyBackendFieldErrors,
  BACKEND_FIELD_ERROR_KEY,
  clearBackendFieldErrors,
} from './apply-backend-field-errors';

describe('applyBackendFieldErrors', () => {
  it('applies normalized backend field errors to matching controls', () => {
    const form = new FormGroup({
      email: new FormControl(''),
      password: new FormControl(''),
    });

    const applied = applyBackendFieldErrors(form, {
      status: 422,
      code: BACKEND_ERROR_CODES.validationError,
      message: 'Validation failed',
      requestId: 'req-1',
      fieldErrors: {
        email: ['Email already exists'],
      },
      meta: null,
      isStandardized: true,
      original: null,
    });

    expect(applied).toBe(true);
    expect(form.get('email')?.errors?.[BACKEND_FIELD_ERROR_KEY]).toBe('Email already exists');
  });

  it('normalizes legacy backend errors before applying field errors', () => {
    const form = new FormGroup({
      email: new FormControl(''),
    });

    const applied = applyBackendFieldErrors(form, {
      status: 422,
      error: {
        errors: {
          email: ['The email has already been taken.'],
        },
      },
    });

    expect(applied).toBe(true);
    expect(form.get('email')?.errors?.[BACKEND_FIELD_ERROR_KEY]).toBe('The email has already been taken.');
  });

  it('supports nested control paths and preserves existing client errors', () => {
    const form = new FormGroup({
      profile: new FormGroup({
        email: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
      }),
    });

    const control = form.get('profile.email');
    control?.setErrors({ required: true });

    const applied = applyBackendFieldErrors(form, {
      status: 422,
      code: BACKEND_ERROR_CODES.validationError,
      message: 'Validation failed',
      requestId: 'req-2',
      fieldErrors: {
        'profile.email': ['Email already exists'],
      },
      meta: null,
      isStandardized: true,
      original: null,
    });

    expect(applied).toBe(true);
    expect(control?.errors).toEqual({
      required: true,
      [BACKEND_FIELD_ERROR_KEY]: 'Email already exists',
    });
    expect(control?.touched).toBe(true);
  });

  it('clears only backend field errors and preserves other validation errors', () => {
    const form = new FormGroup({
      email: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    });

    form.get('email')?.setErrors({
      required: true,
      [BACKEND_FIELD_ERROR_KEY]: 'Email already exists',
    });

    clearBackendFieldErrors(form);

    expect(form.get('email')?.errors).toEqual({ required: true });
  });

  it('clears backend field errors recursively from nested groups and arrays', () => {
    const form = new FormGroup({
      profile: new FormGroup({
        email: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
      }),
      aliases: new FormArray([
        new FormControl('alias-1'),
      ]),
    });

    form.get('profile.email')?.setErrors({
      required: true,
      [BACKEND_FIELD_ERROR_KEY]: 'Email already exists',
    });
    form.get('aliases.0')?.setErrors({
      [BACKEND_FIELD_ERROR_KEY]: 'Alias is duplicated',
    });

    clearBackendFieldErrors(form);

    expect(form.get('profile.email')?.errors).toEqual({ required: true });
    expect(form.get('aliases.0')?.errors).toBeNull();
  });
});
