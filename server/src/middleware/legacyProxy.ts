import type { Request, Response } from 'express';
import type { ClientRequest } from 'http';
import { createProxyMiddleware, type Options } from 'http-proxy-middleware';
import { orchestratorConfig } from '../config.js';

const PHP_ROUTE_REGEX = /\.php$/i;

type ExpressRequest = Request;
type ExpressResponse = Response;

const shouldProxyRequest = (pathname: string, _req: ExpressRequest): boolean => PHP_ROUTE_REGEX.test(pathname);

const bufferBody = (body: unknown): Buffer | undefined => {
  if (body === undefined || body === null) {
    return undefined;
  }

  if (Buffer.isBuffer(body)) {
    return body;
  }

  if (typeof body === 'string') {
    return Buffer.from(body);
  }

  if (typeof body === 'object') {
    try {
      return Buffer.from(JSON.stringify(body));
    } catch (err) {
      return Buffer.from(String(body));
    }
  }

  return Buffer.from(String(body));
};

const isExpressResponse = (res: unknown): res is ExpressResponse =>
  typeof res === 'object' && res !== null && typeof (res as ExpressResponse).status === 'function';

export function createLegacyProxyMiddleware(baseUrl: string = orchestratorConfig.baseUrl) {
  const options: Options<ExpressRequest, ExpressResponse> = {
    target: baseUrl,
    changeOrigin: true,
    proxyTimeout: orchestratorConfig.timeoutMs,
    selfHandleResponse: false,
    pathFilter: shouldProxyRequest,
    pathRewrite: (path) => path.replace(/^\/api\//i, '/'),
    logger: console,
    on: {
      error(err, _req, res) {
        if (!isExpressResponse(res) || res.headersSent) {
          return;
        }

        const statusCandidate = (err as any)?.statusCode;
        const status = typeof statusCandidate === 'number' ? statusCandidate : 502;
        const message = err instanceof Error ? err.message : 'Legacy proxy request failed';

        res
          .status(status)
          .json({ status: 'error', message, code: 'LEGACY_PROXY_ERROR' });
      },
      proxyReq(proxyReq: ClientRequest, req: ExpressRequest) {
        if (req.method === 'GET' || req.method === 'HEAD') {
          return;
        }

        const hasBody =
          req.body &&
          typeof req.body === 'object' &&
          Object.keys(req.body as Record<string, unknown>).length > 0;
        if (!hasBody) {
          return;
        }

        const bufferedBody = bufferBody(req.body);
        if (!bufferedBody) {
          return;
        }

        const contentType = req.headers['content-type'];
        if (contentType && !proxyReq.getHeader('content-type')) {
          proxyReq.setHeader('content-type', contentType);
        }

        proxyReq.setHeader('content-length', Buffer.byteLength(bufferedBody).toString());
        proxyReq.write(bufferedBody);
      },
    },
  };

  return createProxyMiddleware<ExpressRequest, ExpressResponse>(options);
}
