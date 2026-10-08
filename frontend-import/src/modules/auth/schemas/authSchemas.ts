import { z } from 'zod';

function parseNullableString(value: unknown): string | null {
  if (value === null || value === undefined) {
    return null;
  }

  if (typeof value === 'string') {
    return value;
  }

  if (typeof value === 'number') {
    return String(value);
  }

  return null;
}

export type AuthUser = {
  id: string;
  name: string;
  email: string;
  isEmailVerified: boolean;
  phoneNumber: string | null;
  emailVerifiedAt: string | null;
  telegramChatId: string | null;
};

export type AuthPostAuthAction = 'account-invites';

export type AuthPostAuthRedirect = {
  action: AuthPostAuthAction;
  url: string;
};

export type AuthSession = {
  user: AuthUser;
  token: string;
  tokenType: string;
  expiresAt: string;
  postAuthRedirect: AuthPostAuthRedirect | null;
};

export type LoginFormValues = {
  email: string;
  password: string;
  postAuthAction?: AuthPostAuthAction;
};

export type RegisterFormValues = {
  name: string;
  email: string;
  phoneNumber: string;
  password: string;
  passwordConfirmation: string;
  termsAccepted: boolean;
  privacyPolicyAccepted: boolean;
  postAuthAction?: AuthPostAuthAction;
};

export type PasswordRecoveryFormValues = {
  email: string;
};

export type PasswordResetFormValues = {
  token: string;
  email: string;
  password: string;
  passwordConfirmation: string;
};

export const loginFormSchema = z.object({
  email: z.string().trim().email('Ingresa un correo electrónico válido.'),
  password: z.string().trim().min(1, 'La contraseña es obligatoria.'),
  postAuthAction: z.literal('account-invites').optional(),
});

export const registerFormSchema = z
  .object({
    name: z.string().trim().min(1, 'El nombre es obligatorio.'),
    email: z.string().trim().email('Ingresa un correo electrónico válido.'),
    phoneNumber: z.string().trim(),
    password: z.string().trim().min(8, 'La contraseña debe tener al menos 8 caracteres.'),
    passwordConfirmation: z.string().trim().min(1, 'Confirma tu contraseña.'),
    termsAccepted: z.boolean().refine((value) => value, 'Debes aceptar los términos.'),
    privacyPolicyAccepted: z
      .boolean()
      .refine((value) => value, 'Debes aceptar la política de privacidad.'),
    postAuthAction: z.literal('account-invites').optional(),
  })
  .superRefine((values, context) => {
    if (values.password !== values.passwordConfirmation) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['passwordConfirmation'],
        message: 'La confirmación debe coincidir con la contraseña.',
      });
    }
  });

export const passwordRecoveryFormSchema = z.object({
  email: z.string().trim().email('Ingresa un correo electrónico válido.'),
});

export const passwordResetFormSchema = z
  .object({
    token: z.string().trim().min(1, 'El token es obligatorio.'),
    email: z.string().trim().email('Ingresa un correo electrónico válido.'),
    password: z.string().trim().min(8, 'La contraseña debe tener al menos 8 caracteres.'),
    passwordConfirmation: z.string().trim().min(1, 'Confirma tu contraseña.'),
  })
  .superRefine((values, context) => {
    if (values.password !== values.passwordConfirmation) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['passwordConfirmation'],
        message: 'La confirmación debe coincidir con la contraseña.',
      });
    }
  });

export const authUserApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  name: z.string(),
  email: z.string().email(),
  is_email_verified: z.boolean(),
  email_verified_at: z
    .string()
    .nullable()
    .optional()
    .transform((value) => value ?? null),
  phone_number: z
    .string()
    .nullable()
    .optional()
    .transform((value) => value ?? null),
  telegram_chat_id: z
    .union([z.string(), z.number(), z.null()])
    .optional()
    .transform((value) => parseNullableString(value)),
});

const postAuthRedirectApiSchema = z.object({
  action: z.literal('account-invites'),
  url: z.string().min(1),
});

export const authResponseApiSchema = z.object({
  message: z.string().optional(),
  data: authUserApiSchema,
  meta: z.object({
    auth: z.object({
      token: z.string(),
      expires_at: z.string(),
      token_type: z.string(),
    }),
    post_auth_redirect: postAuthRedirectApiSchema.optional(),
  }),
});

export function mapAuthUserApiToDomain(payload: z.infer<typeof authUserApiSchema>): AuthUser {
  return {
    id: payload.id,
    name: payload.name,
    email: payload.email,
    isEmailVerified: payload.is_email_verified,
    phoneNumber: payload.phone_number,
    emailVerifiedAt: payload.email_verified_at,
    telegramChatId: payload.telegram_chat_id,
  };
}

export function mapAuthResponseApiToSession(
  payload: z.infer<typeof authResponseApiSchema>,
): AuthSession {
  return {
    user: mapAuthUserApiToDomain(payload.data),
    token: payload.meta.auth.token,
    tokenType: payload.meta.auth.token_type,
    expiresAt: payload.meta.auth.expires_at,
    postAuthRedirect: payload.meta.post_auth_redirect
      ? {
          action: payload.meta.post_auth_redirect.action,
          url: payload.meta.post_auth_redirect.url,
        }
      : null,
  };
}

export function createDefaultLoginFormValues(): LoginFormValues {
  return {
    email: '',
    password: '',
  };
}

export function createDefaultRegisterFormValues(): RegisterFormValues {
  return {
    name: '',
    email: '',
    phoneNumber: '',
    password: '',
    passwordConfirmation: '',
    termsAccepted: false,
    privacyPolicyAccepted: false,
  };
}

export function createDefaultPasswordRecoveryFormValues(): PasswordRecoveryFormValues {
  return {
    email: '',
  };
}

export function createDefaultPasswordResetFormValues(
  token = '',
  email = '',
): PasswordResetFormValues {
  return {
    token,
    email,
    password: '',
    passwordConfirmation: '',
  };
}
