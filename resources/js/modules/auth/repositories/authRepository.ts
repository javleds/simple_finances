import { z } from 'zod';

import { clearStoredAuthToken, createApiClient, setStoredAuthToken } from '@/lib/api/apiClient';
import {
  clearPendingVerificationEmail,
  clearStoredAuthSession,
  setPendingVerificationEmail,
  setStoredAuthSession,
} from '@/modules/auth/lib/authSession';

import {
  authResponseApiSchema,
  mapAuthResponseApiToSession,
  type AuthSession,
  type LoginFormValues,
  type PasswordRecoveryFormValues,
  type PasswordResetFormValues,
  type RegisterFormValues,
} from '../schemas/authSchemas';

const apiClient = createApiClient();

const messageResponseSchema = z
  .union([z.object({ message: z.string() }), z.object({ data: z.object({ message: z.string() }) })])
  .transform((payload) => ('data' in payload ? payload.data : payload));

function buildLoginPayload(payload: LoginFormValues) {
  return {
    email: payload.email,
    password: payload.password,
    post_auth_action: payload.postAuthAction,
  };
}

function buildRegisterPayload(payload: RegisterFormValues) {
  return {
    name: payload.name,
    email: payload.email,
    password: payload.password,
    password_confirmation: payload.passwordConfirmation,
    phone_number: payload.phoneNumber.trim() || null,
    terms_accepted: payload.termsAccepted,
    privacy_policy_accepted: payload.privacyPolicyAccepted,
    post_auth_action: payload.postAuthAction,
  };
}

function buildPasswordRecoveryPayload(payload: PasswordRecoveryFormValues) {
  return {
    email: payload.email,
  };
}

function buildEmailVerificationNotificationByEmailPayload(email: string) {
  return {
    email,
  };
}

function buildPasswordResetPayload(payload: PasswordResetFormValues) {
  return {
    token: payload.token,
    email: payload.email,
    password: payload.password,
    password_confirmation: payload.passwordConfirmation,
  };
}

function storeSession(session: AuthSession): AuthSession {
  setStoredAuthToken(session.token);
  setStoredAuthSession(session);

  if (session.user.isEmailVerified) {
    clearPendingVerificationEmail();
    return session;
  }

  setPendingVerificationEmail(session.user.email);
  return session;
}

export function createAuthRepository() {
  return {
    async login(payload: LoginFormValues): Promise<AuthSession> {
      const response = await apiClient.post<unknown>('/auth/login', buildLoginPayload(payload));
      return storeSession(mapAuthResponseApiToSession(authResponseApiSchema.parse(response)));
    },
    async register(payload: RegisterFormValues): Promise<AuthSession> {
      const response = await apiClient.post<unknown>(
        '/auth/register',
        buildRegisterPayload(payload),
      );
      return storeSession(mapAuthResponseApiToSession(authResponseApiSchema.parse(response)));
    },
    async logout(): Promise<void> {
      try {
        await apiClient.delete('/auth/logout');
      } finally {
        clearStoredAuthToken();
        clearStoredAuthSession();
        clearPendingVerificationEmail();
      }
    },
    async requestPasswordRecovery(payload: PasswordRecoveryFormValues): Promise<string> {
      const response = await apiClient.post<unknown>(
        '/auth/password-recovery',
        buildPasswordRecoveryPayload(payload),
      );
      return messageResponseSchema.parse(response).message;
    },
    async resetPassword(payload: PasswordResetFormValues): Promise<string> {
      const response = await apiClient.put<unknown>(
        '/auth/password-reset',
        buildPasswordResetPayload(payload),
      );
      return messageResponseSchema.parse(response).message;
    },
    async resendEmailVerification(): Promise<string> {
      const response = await apiClient.post<unknown>('/auth/email-verification-notification');
      return messageResponseSchema.parse(response).message;
    },
    async resendEmailVerificationByEmail(email: string): Promise<string> {
      const response = await apiClient.post<unknown>(
        '/auth/email-verification-notification-by-email',
        buildEmailVerificationNotificationByEmailPayload(email),
      );
      return messageResponseSchema.parse(response).message;
    },
  };
}
