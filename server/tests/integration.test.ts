import { spawn, type ChildProcess } from 'child_process';
import path from 'node:path';
import axios from 'axios';
import supertest from 'supertest';
import { createApp } from '../src/app.js';
import { OrchestratorClient } from '../src/services/orchestratorClient.js';
import type { Express } from 'express';

describe('API integration', () => {
  const phpPort = 8091;
  let phpServer: ChildProcess | undefined;
  let app: Express;

  const waitForPhp = async () => {
    const baseUrl = `http://127.0.0.1:${phpPort}`;
    for (let attempt = 0; attempt < 20; attempt += 1) {
      try {
        await axios.get(`${baseUrl}/health_endpoint.php`, { timeout: 1000 });
        return;
      } catch (err) {
        await new Promise((resolve) => setTimeout(resolve, 250));
      }
    }
    throw new Error('PHP server failed to start');
  };

  beforeAll(async () => {
    const projectRoot = path.resolve(process.cwd(), '..');

    phpServer = spawn('php', ['-S', `127.0.0.1:${phpPort}`, '-t', 'api'], {
      cwd: projectRoot,
      stdio: 'ignore',
    });

    await waitForPhp();

    const client = new OrchestratorClient({
      baseUrl: `http://127.0.0.1:${phpPort}`,
      timeoutMs: 10000,
    });

    app = createApp(client);
  }, 30000);

  afterAll(() => {
    if (phpServer) {
      phpServer.kill();
      phpServer = undefined;
    }
  });

  test('GET /dashboard returns live metrics', async () => {
    const response = await supertest(app).get('/dashboard');
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('ok');
    expect(response.body.data).toMatchObject({
      proxy_stats: expect.any(Object),
      status_codes: expect.any(Object),
    });
  });

  test('GET /test-runs returns runs list', async () => {
    const response = await supertest(app).get('/test-runs');
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('ok');
    expect(Array.isArray(response.body.data)).toBe(true);
  });

  test('GET /test-plans returns plan list', async () => {
    const response = await supertest(app).get('/test-plans');
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('ok');
    expect(Array.isArray(response.body.data)).toBe(true);
  });

  test('GET /reports returns reports list', async () => {
    const response = await supertest(app).get('/reports');
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('ok');
    expect(Array.isArray(response.body.data)).toBe(true);
  });

  test('GET /test-runs/:id returns 404 for missing run', async () => {
    const response = await supertest(app).get('/test-runs/unknown-run-id');
    expect(response.status).toBe(404);
    expect(response.body.status).toBe('error');
    expect(response.body.error.message).toMatch(/not found/i);
  });

  test('GET /api/metrics_endpoint.php returns legacy metrics payload', async () => {
    const response = await supertest(app).get('/api/metrics_endpoint.php');
    expect(response.status).toBe(200);
    expect(response.body.success).toBe(true);
    expect(response.body.metrics).toMatchObject({
      requests_per_second: expect.any(Number),
      total_requests: expect.any(Number),
    });
  });

  test('GET /api/runs_endpoint.php returns legacy runs payload', async () => {
    const response = await supertest(app).get('/api/runs_endpoint.php').query({ limit: 3 });
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('success');
    expect(Array.isArray(response.body.data?.runs)).toBe(true);
  });

  test('GET /api/runs_endpoint.php handles missing run via legacy format', async () => {
    const response = await supertest(app).get('/api/runs_endpoint.php').query({ run_id: 'unknown-run-id' });
    expect(response.status).toBe(404);
    expect(response.body.status).toBe('error');
    expect(typeof response.body.message).toBe('string');
  });

  test('GET /api/group_runs_endpoint.php returns legacy plan payload', async () => {
    const response = await supertest(app)
      .get('/api/group_runs_endpoint.php')
      .query({ action: 'list', limit: 5 });
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('success');
    expect(Array.isArray(response.body.data?.groups)).toBe(true);
  });

  test('GET /api/reports_endpoint.php returns legacy reports payload', async () => {
    const response = await supertest(app).get('/api/reports_endpoint.php').query({ action: 'list' });
    expect(response.status).toBe(200);
    expect(response.body.status).toBe('success');
    expect(Array.isArray(response.body.data?.reports)).toBe(true);
  });
});
