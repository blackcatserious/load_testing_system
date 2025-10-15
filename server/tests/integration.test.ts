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
});
