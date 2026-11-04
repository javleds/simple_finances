import type { AuthSession } from '../schemas/authSchemas';

const AUTH_SESSION_STORAGE_KEY = 'finsi_20_auth_session';
const PENDING_VERIFICATION_EMAIL_STORAGE_KEY = 'finsi_20_pending_verification_email';

function canAccessStorage(): boolean {
  return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined';
}

export function getStoredAuthSession(): AuthSession | null {
  if (!canAccessStorage()) {
    return null;
  }

  const rawValue = window.localStorage.getItem(AUTH_SESSION_STORAGE_KEY);

  if (!rawValue) {
    return null;
  }

  try {
    return JSON.parse(rawValue) as AuthSession;
  } catch {
    window.localStorage.removeItem(AUTH_SESSION_STORAGE_KEY);
    return null;
  }
}

export function setStoredAuthSession(session: AuthSession): void {
  if (!canAccessStorage()) {
    return;
  }

  window.localStorage.setItem(AUTH_SESSION_STORAGE_KEY, JSON.stringify(session));
}

export function clearStoredAuthSession(): void {
  if (!canAccessStorage()) {
    return;
  }

  window.localStorage.removeItem(AUTH_SESSION_STORAGE_KEY);
}

export function getPendingVerificationEmail(): string | null {
  if (!canAccessStorage()) {
    return null;
  }

  return window.localStorage.getItem(PENDING_VERIFICATION_EMAIL_STORAGE_KEY);
}

export function setPendingVerificationEmail(email: string): void {
  if (!canAccessStorage()) {
    return;
  }

  window.localStorage.setItem(PENDING_VERIFICATION_EMAIL_STORAGE_KEY, email);
}

export function clearPendingVerificationEmail(): void {
  if (!canAccessStorage()) {
    return;
  }

  window.localStorage.removeItem(PENDING_VERIFICATION_EMAIL_STORAGE_KEY);
}
