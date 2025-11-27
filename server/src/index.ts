import { createServer } from 'http';
import { createApp } from './app.js';
import { orchestratorConfig } from './config.js';

const port = process.env.PORT ? Number(process.env.PORT) : 4000;

const app = createApp();
const server = createServer(app);

server.listen(port, () => {
  // eslint-disable-next-line no-console
  console.log(`API server listening on port ${port} (orchestrator: ${orchestratorConfig.baseUrl})`);
});
