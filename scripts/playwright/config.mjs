import path from 'node:path';

export const defaultConfig = {
  appBaseUrl: process.env.PW_APP_URL ?? 'http://127.0.0.1:8000',
  apiBaseUrl: process.env.PW_API_URL ?? 'http://127.0.0.1:8000/api',
  email: process.env.PW_EMAIL ?? 'test@example.com',
  password: process.env.PW_PASSWORD ?? 'password',
  outputDir: process.env.PW_SCREENSHOT_DIR ?? path.join('tmp', 'playwright-facilities'),
};

export const viewports = {
  mobile: {
    width: 390,
    height: 844,
    deviceScaleFactor: 2,
    isMobile: true,
  },
  desktop: {
    width: 1440,
    height: 1100,
    deviceScaleFactor: 1,
    isMobile: false,
  },
};

export function resolveUrl(baseUrl, pathName) {
  return new URL(pathName, `${baseUrl.replace(/\/$/, '')}/`).toString();
}
