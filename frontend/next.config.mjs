import {fileURLToPath} from 'node:url';
export default (phase) => ({
  outputFileTracingRoot:fileURLToPath(new URL('.',import.meta.url)),
  distDir:phase === 'phase-development-server' ? '.next-dev' : '.next',
});
