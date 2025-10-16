import { Router } from 'express';
import type { OrchestratorClient } from '../services/orchestratorClient.js';
import type {
  ApiResponse,
  StartTestRequest,
  StartTestResponse,
  StopTestRequest,
  StopTestResponse,
} from '../types/dto.js';
import { OrchestratorError } from '../utils/errors.js';

const normalizeTargets = (targets: unknown): string[] => {
  if (!Array.isArray(targets)) {
    return [];
  }

  return targets
    .map((target) => (typeof target === 'string' ? target.trim() : String(target)))
    .filter((target) => target.length > 0);
};

const coerceNumber = (value: unknown, fallback: number): number => {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string') {
    const parsed = Number(value);
    if (Number.isFinite(parsed)) {
      return parsed;
    }
  }

  return fallback;
};

const buildStartPayload = (body: unknown): StartTestRequest => {
  if (!body || typeof body !== 'object') {
    throw new OrchestratorError('Invalid start payload', {
      statusCode: 400,
      code: 'INVALID_START_PAYLOAD',
      details: body,
    });
  }

  const raw = body as Record<string, unknown>;

  const normalizedTargets = normalizeTargets(raw.targets);
  const targetUrl = typeof raw.target_url === 'string' ? raw.target_url.trim() : undefined;

  if (!targetUrl && normalizedTargets.length === 0) {
    throw new OrchestratorError('Provide at least one target URL to start a run', {
      statusCode: 400,
      code: 'MISSING_TARGETS',
    });
  }

  const profileId = typeof raw.profile_id === 'string' ? raw.profile_id : undefined;
  const engine = typeof raw.engine === 'string' ? raw.engine : undefined;

  if (!profileId || !engine) {
    throw new OrchestratorError('profile_id and engine are required to start a run', {
      statusCode: 400,
      code: 'MISSING_PARAMETERS',
    });
  }

  const payload: StartTestRequest = {
    profile_id: profileId,
    engine,
    threads: coerceNumber(raw.threads, 0),
    duration: coerceNumber(raw.duration, 0),
    behavior_profile_id:
      typeof raw.behavior_profile_id === 'string' ? raw.behavior_profile_id : undefined,
    attack_method: typeof raw.attack_method === 'string' ? raw.attack_method : undefined,
    stealth_profile: typeof raw.stealth_profile === 'string' ? raw.stealth_profile : undefined,
    proxy_profile: typeof raw.proxy_profile === 'string' ? raw.proxy_profile : undefined,
    user_agent_rotation: raw.user_agent_rotation === undefined ? undefined : Boolean(raw.user_agent_rotation),
    ja3_rotation: raw.ja3_rotation === undefined ? undefined : Boolean(raw.ja3_rotation),
    tls_rotation: raw.tls_rotation === undefined ? undefined : Boolean(raw.tls_rotation),
    proxy_rotation: raw.proxy_rotation === undefined ? undefined : Boolean(raw.proxy_rotation),
    spoof_headers: raw.spoof_headers === undefined ? undefined : Boolean(raw.spoof_headers),
  };

  if (targetUrl) {
    payload.target_url = targetUrl;
  }

  if (normalizedTargets.length > 0) {
    payload.targets = normalizedTargets;
  }

  return payload;
};

const buildStopPayload = (body: unknown): StopTestRequest => {
  if (!body || typeof body !== 'object') {
    throw new OrchestratorError('Invalid stop payload', {
      statusCode: 400,
      code: 'INVALID_STOP_PAYLOAD',
      details: body,
    });
  }

  const raw = body as Record<string, unknown>;
  const groupId = typeof raw.group_id === 'string' ? raw.group_id.trim() : undefined;
  const runId = typeof raw.run_id === 'string' ? raw.run_id.trim() : undefined;

  if (!groupId && !runId) {
    throw new OrchestratorError('Provide either group_id or run_id to stop a run', {
      statusCode: 400,
      code: 'MISSING_IDENTIFIER',
    });
  }

  const payload: StopTestRequest = {};
  if (groupId) {
    payload.group_id = groupId;
  }
  if (runId) {
    payload.run_id = runId;
  }

  return payload;
};

export function createControlRouter(client: OrchestratorClient): Router {
  const router = Router();

  router.post('/start', async (req, res, next) => {
    try {
      const payload = buildStartPayload(req.body);
      const responsePayload = await client.startTest(payload);
      const response: ApiResponse<StartTestResponse> = {
        status: 'ok',
        data: responsePayload,
      };
      res.json(response);
    } catch (err) {
      next(err);
    }
  });

  router.post('/stop', async (req, res, next) => {
    try {
      const payload = buildStopPayload(req.body);
      const responsePayload = await client.stopTest(payload);
      const response: ApiResponse<StopTestResponse> = {
        status: 'ok',
        data: responsePayload,
      };
      res.json(response);
    } catch (err) {
      next(err);
    }
  });

  return router;
}
