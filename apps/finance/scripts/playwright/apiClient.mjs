function normalizeApiBaseUrl(apiBaseUrl) {
  return apiBaseUrl.replace(/\/$/, '');
}

export async function apiRequest(apiBaseUrl, pathName, options = {}) {
  const response = await fetch(`${normalizeApiBaseUrl(apiBaseUrl)}${pathName}`, {
    method: options.method ?? 'GET',
    headers: {
      Accept: 'application/json',
      ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  const payload = await parseResponse(response);

  if (!response.ok) {
    const message =
      typeof payload === 'object' &&
      payload !== null &&
      'message' in payload &&
      typeof payload.message === 'string'
        ? payload.message
        : `API request failed with status ${response.status}.`;

    throw new Error(`${message} (${pathName})`);
  }

  return payload;
}

async function parseResponse(response) {
  const contentType = response.headers.get('content-type') ?? '';

  if (response.status === 204) {
    return null;
  }

  if (contentType.includes('application/json')) {
    return response.json();
  }

  return response.text();
}
