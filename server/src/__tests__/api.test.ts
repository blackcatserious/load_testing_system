import request from 'supertest';

process.env.NODE_ENV = 'test';

import app from '../index';

type FetchOptions = {
  method?: string;
  headers?: Record<string, string>;
  body?: string;
};

const orchestratorBase = process.env.ORCHESTRATOR_BASE_URL ?? 'http://127.0.0.1:9123/api';

const callOrchestrator = async (path: string, init?: FetchOptions) => {
  const url = new URL(path, orchestratorBase.endsWith('/') ? orchestratorBase : `${orchestratorBase}/`);
  const response = await fetch(url, {
    headers: { 'Content-Type': 'application/json', ...(init?.headers ?? {}) },
    method: init?.method ?? 'GET',
    body: init?.body,
  });
  if (!response.ok) {
    throw new Error(`Failed to call orchestrator ${url}: ${response.status}`);
  }
  return response.json();
};

describe('Express orchestration integration', () => {
  let runId: string;
  let groupId: string;

  beforeAll(async () => {
    const payload = await callOrchestrator('start_endpoint.php', {
      method: 'POST',
      body: JSON.stringify({
        target_url: 'https://example.com',
        threads: 10,
        duration: 60,
      }),
    });
    runId = payload.data.run_ids[0];
    groupId = payload.data.group_id;
  });

  it('returns dashboard metrics', async () => {
    const response = await request(app).get('/dashboard');
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('success');
    expect(response.body.data.metrics).toBeDefined();
  });

  it('lists test runs', async () => {
    const response = await request(app).get('/test-runs');
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('success');
    expect(Array.isArray(response.body.data)).toBe(true);
    expect(response.body.data.length).toBeGreaterThan(0);
  });

  it('returns test run details', async () => {
    const response = await request(app).get(`/test-runs/${runId}`);
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('success');
    expect(response.body.data.id).toBe(runId);
  });

  it('lists test plans', async () => {
    const response = await request(app).get('/test-plans');
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('success');
    expect(Array.isArray(response.body.data)).toBe(true);
    expect(response.body.data.length).toBeGreaterThan(0);
  });

  it('returns test plan details', async () => {
    const response = await request(app).get(`/test-plans/${groupId}`);
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('success');
    expect(response.body.data.id).toBe(groupId);
  });

  it('lists reports', async () => {
    const response = await request(app).get('/reports');
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('success');
    expect(Array.isArray(response.body.data)).toBe(true);
  });
});
