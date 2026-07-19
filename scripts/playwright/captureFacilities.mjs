import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';

import { installAuthSession, login } from './auth.mjs';
import { defaultConfig, resolveUrl, viewports } from './config.mjs';
import { resolveFacilities } from './facilityCatalog.mjs';

const args = parseArgs(process.argv.slice(2));
const config = {
  ...defaultConfig,
  appBaseUrl: args.appUrl ?? defaultConfig.appBaseUrl,
  apiBaseUrl: args.apiUrl ?? defaultConfig.apiBaseUrl,
  email: args.email ?? defaultConfig.email,
  password: args.password ?? defaultConfig.password,
  outputDir: args.outputDir ?? defaultConfig.outputDir,
};

const selectedViewportKeys = args.viewport === 'both' ? ['mobile', 'desktop'] : [args.viewport];

if (args.help) {
  printHelp();
  process.exit(0);
}

const authSession = args.list && args.baseOnly ? null : await login(config);
const facilities = await resolveFacilities(config, authSession?.token, {
  includeDynamic: !args.baseOnly,
});

if (args.list) {
  printFacilities(facilities);
  process.exit(0);
}

const selectedFacilities = selectFacilities(facilities, args);

if (selectedFacilities.length === 0) {
  console.error('No facilities matched the provided filters.');
  printFacilities(facilities);
  process.exit(1);
}

await fs.mkdir(config.outputDir, { recursive: true });

const browser = await chromium.launch({ headless: !args.headed });

try {
  for (const viewportKey of selectedViewportKeys) {
    const viewport = viewports[viewportKey];

    for (const facility of selectedFacilities) {
      const context = await browser.newContext({
        viewport: {
          width: viewport.width,
          height: viewport.height,
        },
        deviceScaleFactor: viewport.deviceScaleFactor,
        isMobile: viewport.isMobile,
      });
      const page = await context.newPage();

      try {
        if (facility.auth) {
          if (!authSession) {
            throw new Error(`Facility "${facility.key}" requires an authenticated session.`);
          }

          await installAuthSession(page, authSession);
        }

        await visitFacility(page, facility, viewportKey, config, args);
      } finally {
        await context.close();
      }
    }
  }
} finally {
  await browser.close();
}

function parseArgs(rawArgs) {
  const parsed = {
    all: false,
    facilityKeys: [],
    headed: false,
    help: false,
    list: false,
    baseOnly: false,
    screenshot: true,
    viewport: 'mobile',
  };

  for (let index = 0; index < rawArgs.length; index += 1) {
    const arg = rawArgs[index];
    const nextValue = rawArgs[index + 1];

    if (arg === '--all') {
      parsed.all = true;
      continue;
    }

    if (arg === '--headed') {
      parsed.headed = true;
      continue;
    }

    if (arg === '--help' || arg === '-h') {
      parsed.help = true;
      continue;
    }

    if (arg === '--list') {
      parsed.list = true;
      continue;
    }

    if (arg === '--base-only') {
      parsed.baseOnly = true;
      continue;
    }

    if (arg === '--no-screenshot') {
      parsed.screenshot = false;
      continue;
    }

    if (arg === '--facility' && nextValue) {
      parsed.facilityKeys.push(nextValue);
      index += 1;
      continue;
    }

    if (arg === '--viewport' && nextValue) {
      parsed.viewport = resolveViewport(nextValue);
      index += 1;
      continue;
    }

    if (arg === '--app-url' && nextValue) {
      parsed.appUrl = nextValue;
      index += 1;
      continue;
    }

    if (arg === '--api-url' && nextValue) {
      parsed.apiUrl = nextValue;
      index += 1;
      continue;
    }

    if (arg === '--email' && nextValue) {
      parsed.email = nextValue;
      index += 1;
      continue;
    }

    if (arg === '--password' && nextValue) {
      parsed.password = nextValue;
      index += 1;
      continue;
    }

    if (arg === '--output-dir' && nextValue) {
      parsed.outputDir = nextValue;
      index += 1;
      continue;
    }

    throw new Error(`Unknown or incomplete argument: ${arg}`);
  }

  return parsed;
}

function resolveViewport(value) {
  if (value === 'mobile' || value === 'desktop' || value === 'both') {
    return value;
  }

  throw new Error(`Invalid viewport "${value}". Use mobile, desktop, or both.`);
}

function selectFacilities(facilities, selectedArgs) {
  if (selectedArgs.all || selectedArgs.facilityKeys.length === 0) {
    return facilities;
  }

  const selectedKeys = new Set(selectedArgs.facilityKeys);
  return facilities.filter((facility) => selectedKeys.has(facility.key));
}

async function visitFacility(page, facility, viewportKey, currentConfig, selectedArgs) {
  const url = resolveUrl(currentConfig.appBaseUrl, facility.path);
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(300);

  console.log(`${viewportKey}: ${facility.key} -> ${url}`);

  if (!selectedArgs.screenshot) {
    return;
  }

  const filename = `${viewportKey}-${facility.key}.png`;
  const screenshotPath = path.join(currentConfig.outputDir, filename);
  await page.screenshot({ path: screenshotPath, fullPage: true });
}

function printFacilities(facilities) {
  console.log('Available views:');

  for (const facility of facilities) {
    console.log(`- ${facility.key.padEnd(22)} ${facility.path}`);
  }
}

function printHelp() {
  console.log(`
Usage:
  npm run pw:views:list
  npm run pw:views -- --all
  npm run pw:views -- --facility dashboard
  npm run pw:views -- --facility account-transactions --viewport mobile
  npm run pw:views -- --all --viewport both

Compatibility aliases:
  npm run pw:facilities -- --list
  npm run pw:facilities -- --facility dashboard
  npm run pw:facilities -- --facility account-transactions --viewport mobile
  npm run pw:facilities -- --all --viewport both

Options:
  --list                    Print the view catalog.
  --all                     Visit every view. This is the default when no view is passed.
  --facility <key>          Visit a single view by key. Can be repeated.
  --viewport <value>        mobile, desktop, or both. Defaults to mobile.
  --base-only               Skip dynamic views that require account or rule IDs.
  --headed                  Show the browser.
  --no-screenshot           Visit routes without writing screenshots.
  --output-dir <path>       Screenshot directory. pw:views uses tmp/playwright-views.
  --app-url <url>           Defaults to PW_APP_URL or http://localhost:5173.
  --api-url <url>           Defaults to PW_API_URL or http://localhost:8000/api.
  --email <email>           Defaults to PW_EMAIL or test@example.com.
  --password <password>     Defaults to PW_PASSWORD or password.
`);
}
