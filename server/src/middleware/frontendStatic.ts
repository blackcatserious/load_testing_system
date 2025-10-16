import express from 'express';
import { Router } from 'express';
import fs from 'node:fs';
import path from 'node:path';

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

  if (!distPath || !fs.existsSync(distPath) || !fs.statSync(distPath).isDirectory()) {
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
      next();
      return;
    }

    res.sendFile(indexFile);
  });

  return router;
}
