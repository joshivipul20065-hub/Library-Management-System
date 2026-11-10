import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios';
import type { ApiErrorBody, AuthResponse } from '../types';

export const API_BASE: string = import.meta.env.VITE_API_URL ?? '/api/v1';

// The access token lives only in memory; the refresh token is an httpOnly cookie the browser manages.
let accessToken: string | null = null;
let sessionExpiredHandler: (() => void) | null = null;

export const tokenStore = {
  get: (): string | null => accessToken,
  set: (token: string | null): void => {
    accessToken = token;
  },
};

export function onSessionExpired(handler: (() => void) | null): void {
  sessionExpiredHandler = handler;
}

export const api = axios.create({
  baseURL: API_BASE,
  withCredentials: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
});

api.interceptors.request.use((config) => {
  if (accessToken) {
    config.headers.Authorization = `Bearer ${accessToken}`;
  }
  return config;
});

let refreshInFlight: Promise<AuthResponse> | null = null;

/** Exchanges the refresh cookie for a new access token. Concurrent callers share one request. */
export function refreshSession(): Promise<AuthResponse> {
  refreshInFlight ??= axios
    .post<AuthResponse>(`${API_BASE}/auth/refresh`, null, {
      withCredentials: true,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then((response) => {
      accessToken = response.data.access_token;
      return response.data;
    })
    .finally(() => {
      refreshInFlight = null;
    });
  return refreshInFlight;
}

const NO_RETRY = [
  '/auth/login',
  '/auth/refresh',
  '/auth/logout',
  '/auth/forgot-password',
  '/auth/reset-password',
];
type RetriableConfig = InternalAxiosRequestConfig & { _retried?: boolean };

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const config = error.config as RetriableConfig | undefined;
    if (
      error.response?.status === 401 &&
      config &&
      !config._retried &&
      !NO_RETRY.includes(config.url ?? '')
    ) {
      config._retried = true;
      try {
        await refreshSession();
        return api(config);
      } catch {
        accessToken = null;
        sessionExpiredHandler?.();
      }
    }
    return Promise.reject(error);
  },
);

function errorBody(error: unknown): ApiErrorBody['error'] | null {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as Partial<ApiErrorBody> | undefined;
    if (data?.error?.message) {
      return data.error;
    }
  }
  return null;
}

export function getErrorMessage(
  error: unknown,
  fallback = 'Something went wrong. Please try again.',
): string {
  const body = errorBody(error);
  if (body) {
    return body.message;
  }
  if (axios.isAxiosError(error) && !error.response) {
    return 'Cannot reach the server. Check your connection.';
  }
  return fallback;
}

export function getErrorCode(error: unknown): string | null {
  return errorBody(error)?.code ?? null;
}

/** Field-level validation messages returned by the API (422). */
export function getFieldErrors(error: unknown): Record<string, string> {
  const details = errorBody(error)?.details ?? {};
  return Object.fromEntries(
    Object.entries(details).filter((entry): entry is [string, string] => typeof entry[1] === 'string'),
  );
}
