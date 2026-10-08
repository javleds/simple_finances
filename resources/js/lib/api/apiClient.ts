type ApiRequestOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  headers?: HeadersInit;
  signal?: AbortSignal;
  credentials?: RequestCredentials;
};

const AUTH_TOKEN_STORAGE_KEY = 'finsi_20_auth_token';
let isRedirectingAfterUnauthorized = false;

import {
  clearPendingVerificationEmail,
  clearStoredAuthSession,
} from '@/modules/auth/lib/authSession';

function canAccessStorage(): boolean {
  return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined';
}

export function getStoredAuthToken(): string | null {
  if (!canAccessStorage()) {
    return null;
  }

  return window.localStorage.getItem(AUTH_TOKEN_STORAGE_KEY);
}

export function setStoredAuthToken(token: string): void {
  if (!canAccessStorage()) {
    return;
  }

  window.localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, token);
}

export function clearStoredAuthToken(): void {
  if (!canAccessStorage()) {
    return;
  }

  window.localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY);
}

function clearStoredAuthState(): void {
  clearStoredAuthToken();
  clearStoredAuthSession();
  clearPendingVerificationEmail();
}

function redirectToLogin(): void {
  if (typeof window === 'undefined' || isRedirectingAfterUnauthorized) {
    return;
  }

  isRedirectingAfterUnauthorized = true;
  clearStoredAuthState();
  window.location.assign('/auth');
}

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly payload?: unknown,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

function resolveApiBaseUrl(): string {
  const baseUrl = import.meta.env.VITE_API_BASE_URL?.trim();

  return (baseUrl || '/api').replace(/\/$/, '');
}

function resolveUrl(path: string): string {
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }

  if (path.startsWith('/')) {
    return `${resolveApiBaseUrl()}${path}`;
  }

  return `${resolveApiBaseUrl()}/${path}`;
}

async function parseResponse(response: Response): Promise<unknown> {
  const contentType = response.headers.get('content-type') ?? '';

  if (response.status === 204) {
    return null;
  }

  if (contentType.includes('application/json')) {
    return response.json();
  }

  const text = await response.text();

  if (!text) {
    return null;
  }

  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}

async function request<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  const authToken = getStoredAuthToken();
  const response = await fetch(resolveUrl(path), {
    method: options.method ?? 'GET',
    credentials: options.credentials,
    headers: {
      Accept: 'application/json',
      ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
    signal: options.signal,
  });

  const payload = await parseResponse(response);

  if (!response.ok) {
    if (response.status === 401 && authToken) {
      redirectToLogin();
    }

    const message =
      typeof payload === 'object' &&
      payload !== null &&
      'message' in payload &&
      typeof payload.message === 'string'
        ? payload.message
        : 'Request failed.';

    throw new ApiError(message, response.status, payload);
  }

  return payload as T;
}

export function createApiClient() {
  return {
    get<T>(path: string, options?: Omit<ApiRequestOptions, 'method' | 'body'>) {
      return request<T>(path, { ...options, method: 'GET' });
    },
    post<T>(path: string, body?: unknown, options?: Omit<ApiRequestOptions, 'method' | 'body'>) {
      return request<T>(path, { ...options, method: 'POST', body });
    },
    put<T>(path: string, body?: unknown, options?: Omit<ApiRequestOptions, 'method' | 'body'>) {
      return request<T>(path, { ...options, method: 'PUT', body });
    },
    patch<T>(path: string, body?: unknown, options?: Omit<ApiRequestOptions, 'method' | 'body'>) {
      return request<T>(path, { ...options, method: 'PATCH', body });
    },
    delete<T>(path: string, options?: Omit<ApiRequestOptions, 'method' | 'body'>) {
      return request<T>(path, { ...options, method: 'DELETE' });
    },
  };
}
