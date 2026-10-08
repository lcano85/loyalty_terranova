// Production entry point for cPanel / Passenger. Build with npm run build first.
process.env.NODE_ENV = 'production';
const { createServer } = require('node:http');
const next = require('next');
const app = next({ dev: false, dir: __dirname });
const handler = app.getRequestHandler();

app.prepare().then(() => {
  const server = createServer((req, res) => {
    Promise.resolve(handler(req, res)).catch((error) => {
      console.error('Request failed:', error);
      if (!res.headersSent) res.writeHead(500);
      res.end('Internal server error');
    });
  });
  server.on('error', (error) => {
    console.error('Server failed:', error);
    process.exit(1);
  });
  // Passenger intercepts listen(); outside Passenger, PORT controls the port.
  server.listen(process.env.PORT || 3000, () => console.log('Terranova frontend ready'));
}).catch((error) => {
  console.error('Next.js startup failed. Check the production build:', error);
  process.exit(1);
});
