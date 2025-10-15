import fs from 'fs';
import path from 'path';

const PID_FILE = path.resolve(__dirname, '../../tmp/php-server.pid');

export default async function globalTeardown() {
  if (fs.existsSync(PID_FILE)) {
    const pid = Number(fs.readFileSync(PID_FILE, 'utf-8'));
    try {
      process.kill(pid);
    } catch (error) {
      // ignore failures, process may already be terminated
    }
    fs.unlinkSync(PID_FILE);
  }
}
