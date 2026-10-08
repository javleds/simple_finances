import { z } from 'zod';

export type Profile = {
  id: string;
  name: string;
  email: string;
  isEmailVerified: boolean;
  phoneNumber: string;
  telegramChatId: string | null;
};

export type ProfileFormValues = {
  name: string;
  email: string;
  phoneNumber: string;
  password: string;
  passwordConfirmation: string;
};

export type ProfileWritePayload = {
  name: string;
  email: string;
  phoneNumber: string | null;
  password: string | null;
  passwordConfirmation: string | null;
};

export const profileFormSchema = z
  .object({
    name: z.string().trim().min(1, 'El nombre es obligatorio.'),
    email: z.string().trim().email('Ingresa un correo electrónico válido.'),
    phoneNumber: z.string().trim(),
    password: z.string(),
    passwordConfirmation: z.string(),
  })
  .superRefine((values, context) => {
    if (!values.password.trim()) {
      return;
    }

    if (values.password.trim().length < 8) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['password'],
        message: 'La contraseña debe tener al menos 8 caracteres.',
      });
    }

    if (values.passwordConfirmation.trim().length === 0) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['passwordConfirmation'],
        message: 'Confirma la nueva contraseña.',
      });
      return;
    }

    if (values.password !== values.passwordConfirmation) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['passwordConfirmation'],
        message: 'La confirmación debe coincidir con la contraseña.',
      });
    }
  });

export const profileApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  name: z.string(),
  email: z.string().email(),
  is_email_verified: z.boolean(),
  phone_number: z
    .string()
    .nullable()
    .optional()
    .transform((value) => value ?? ''),
  telegram_chat_id: z
    .union([z.string(), z.number(), z.null(), z.undefined()])
    .transform((value) => (value === null || value === undefined ? null : String(value))),
});

export function createDefaultProfileFormValues(
  profile?: Partial<Profile> | null,
): ProfileFormValues {
  return {
    name: profile?.name ?? '',
    email: profile?.email ?? '',
    phoneNumber: profile?.phoneNumber ?? '',
    password: '',
    passwordConfirmation: '',
  };
}

export function mapProfileApiToDomain(payload: z.infer<typeof profileApiSchema>): Profile {
  return {
    id: payload.id,
    name: payload.name,
    email: payload.email,
    isEmailVerified: payload.is_email_verified,
    phoneNumber: payload.phone_number,
    telegramChatId: payload.telegram_chat_id,
  };
}

export function mapProfileFormToWritePayload(values: ProfileFormValues): ProfileWritePayload {
  const trimmedPassword = values.password.trim();

  return {
    name: values.name.trim(),
    email: values.email.trim(),
    phoneNumber: values.phoneNumber.trim() || null,
    password: trimmedPassword || null,
    passwordConfirmation: trimmedPassword ? values.passwordConfirmation.trim() || null : null,
  };
}
