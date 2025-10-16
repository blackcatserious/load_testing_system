import express from 'express';
import { Router } from 'express';
import fs from 'node:fs';
import path from 'node:path';
import { defaultLandingPageHtml } from '../views/defaultLandingPage.js';

export interface FrontendStaticOptions {
  distPath?: string;
  indexFile?: string;
  apiPrefix?: string;
}

const resolveDistPath = (): string | undefined => {
  const cwd = process.cwd();
  const candidateRoots = [cwd, path.resolve(cwd, '..'), path.resolve(cwd, '../..')];

  for (const root of candidateRoots) {
    const candidate = path.resolve(root, 'frontend_src/dist');
    if (fs.existsSync(candidate) && fs.statSync(candidate).isDirectory()) {
      return candidate;
    }
  }

  return undefined;
};

export function createFrontendStaticMiddleware(options: FrontendStaticOptions = {}): Router {
  const resolvedDistPath = options.distPath ?? resolveDistPath();
  const distPath = resolvedDistPath ?? '';
  const indexFile = options.indexFile ?? (distPath ? path.join(distPath, 'index.html') : undefined);
  const apiPrefix = options.apiPrefix ?? '/api';

  const router = Router();

  const serveFallback = (req: express.Request, res: express.Response, next: express.NextFunction) => {
    if (req.method !== 'GET') {
      next();
      return;
    }

    if (apiPrefix && req.path.startsWith(apiPrefix)) {
      next();
      return;
    }

    res.type('html').send(defaultLandingPageHtml);
  };

  if (!distPath || !fs.existsSync(distPath) || !fs.statSync(distPath).isDirectory()) {
    router.get('*', serveFallback);
    return router;
  }

  router.use(
    express.static(distPath, {
      index: false,
      fallthrough: true,
      extensions: ['html'],
    })
  );

  router.get('*', (req, res, next) => {
    if (req.method !== 'GET') {
      next();
      return;
    }

    if (apiPrefix && req.path.startsWith(apiPrefix)) {
      next();
      return;
    }

    if (!indexFile || req.path.includes('.') || !fs.existsSync(indexFile)) {
      serveFallback(req, res, next);
      return;
    }

    res.sendFile(indexFile);
  });

  return router;
}
