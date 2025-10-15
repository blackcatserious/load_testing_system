import { spawn } from 'child_process';
import fs from 'fs';
import path from 'path';

const TMP_DIR = path.resolve(__dirname, '../../tmp');
const PID_FILE = path.join(TMP_DIR, 'php-server.pid');
const HOST = '127.0.0.1';
const PORT = 9123;

const waitForServer = async (url: string, retries = 20, delayMs = 250): Promise<void> => {
  for (let attempt = 0; attempt < retries; attempt += 1) {
    try {
      const response = await fetch(url);
      if (response.ok) {
        return;
      }
    } catch (error) {
      // ignore and retry
    }
    await new Promise((resolve) => setTimeout(resolve, delayMs));
  }
  throw new Error(`Failed to confirm PHP server at ${url}`);
};

export default async function globalSetup() {
  if (!fs.existsSync(TMP_DIR)) {
    fs.mkdirSync(TMP_DIR, { recursive: true });
  }

  const docRoot = path.resolve(__dirname, '../../..');
  const phpProcess = spawn('php', ['-S', `${HOST}:${PORT}`, '-t', docRoot], {
    stdio: 'ignore',
    env: {
      ...process.env,
      PHP_CLI_SERVER_WORKERS: '1',
    },
  });

  fs.writeFileSync(PID_FILE, String(phpProcess.pid));

  process.env.ORCHESTRATOR_BASE_URL = `http://${HOST}:${PORT}/api`;
  process.env.FALLBACK_METRICS_ENDPOINT = '../working_metrics_endpoint.php';

  await waitForServer(`http://${HOST}:${PORT}/api/health_endpoint.php`);
}
