import { spawn } from 'node:child_process';
import net from 'node:net';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
export const docroot = path.join(here, 'docroot');

export function serverPort() {
  return Number(process.env.DEBUGBAR_BROWSER_PORT || 8765);
}

export function baseUrl() {
  return `http://127.0.0.1:${serverPort()}`;
}

function waitForPort(port, host, timeoutMs) {
  const started = Date.now();
  return new Promise((resolve, reject) => {
    const probe = () => {
      const socket = net.connect(port, host, () => {
        socket.end();
        resolve(true);
      });
      socket.on('error', () => {
        socket.destroy();
        if (Date.now() - started > timeoutMs) {
          reject(new Error(`timed out waiting for ${host}:${port}`));
          return;
        }
        setTimeout(probe, 200);
      });
    };
    probe();
  });
}

/**
 * Starts the test application over PHP's built-in server.
 *
 * One server per invocation: parallel workers must use distinct ports
 * since server processes share no state. The child is killed by
 * stopServer; callers must await it in teardown.
 *
 * @param {number} [port] TCP port, defaults to serverPort().
 * @returns {Promise<import('node:child_process').ChildProcess>} Server process.
 */
export async function startServer(port = serverPort()) {
  const php = process.env.PHP_BINARY || 'php';
  const child = spawn(
    php,
    ['-S', `127.0.0.1:${port}`, path.join(docroot, 'router.php')],
    { cwd: docroot, stdio: ['ignore', 'pipe', 'pipe'] },
  );
  child.stdout.on('data', () => {});
  child.stderr.on('data', () => {});
  await waitForPort(port, '127.0.0.1', 30000);
  return child;
}

export async function stopServer(child) {
  if (!child || child.exitCode !== null) {
    return;
  }
  child.kill();
  await new Promise((resolve) => {
    const done = () => resolve(true);
    child.on('exit', done);
    setTimeout(done, 5000);
  });
}
