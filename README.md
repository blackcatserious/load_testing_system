# Load Testing System Frontend

## Local Development

- Start the Express backend on port **4000**.
- (Optional) Set `VITE_API_PROXY_TARGET` to override the default proxy target.
- Run `npm run dev` from `frontend_src` to start the Vite dev server. The `/api` proxy will forward to `http://localhost:4000` by default.
