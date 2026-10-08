import { apiRequest } from './apiClient.mjs';

const AUTH_TOKEN_STORAGE_KEY = 'finsi_20_auth_token';
const AUTH_SESSION_STORAGE_KEY = 'finsi_20_auth_session';

export async function login(config) {
  const payload = await apiRequest(config.apiBaseUrl, '/auth/login', {
    method: 'POST',
    body: {
      email: config.email,
      password: config.password,
    },
  });

  const token = payload?.meta?.auth?.token;

  if (!token) {
    throw new Error('The login response did not include an auth token.');
  }

  return {
    token,
    tokenType: payload.meta.auth.token_type ?? 'Bearer',
    expiresAt: payload.meta.auth.expires_at ?? null,
    user: {
      id: String(payload.data.id),
      name: payload.data.name,
      email: payload.data.email,
      isEmailVerified: Boolean(payload.data.is_email_verified ?? payload.data.email_verified_at),
      phoneNumber: payload.data.phone_number ?? '',
      telegramChatId: payload.data.telegram_chat_id ?? null,
    },
  };
}

export async function installAuthSession(page, session) {
  await page.addInitScript(
    ({ tokenKey, sessionKey, authSession }) => {
      window.localStorage.setItem(tokenKey, authSession.token);
      window.localStorage.setItem(sessionKey, JSON.stringify(authSession));
    },
    {
      tokenKey: AUTH_TOKEN_STORAGE_KEY,
      sessionKey: AUTH_SESSION_STORAGE_KEY,
      authSession: session,
    },
  );
}
