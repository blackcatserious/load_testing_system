# PulseLoad Platform Server

This lightweight Node.js service powers the sample PulseLoad control center. It exposes
RESTful endpoints that model the workflows of a modern load testing practice: managing
plans, coordinating runs, and distributing reports. Data is served from in-memory
collections so the API can run without external dependencies.

## Available scripts

```bash
npm install     # install dependencies
npm run dev     # start a hot-reloading development server on http://localhost:4000
npm run build   # compile TypeScript to JavaScript
npm start       # serve the compiled JavaScript from dist/
```

## API surface

| Method | Path                    | Description                              |
| ------ | ----------------------- | ---------------------------------------- |
| GET    | `/api/health`           | Service heartbeat                        |
| GET    | `/api/dashboard/overview` | Aggregated control center overview     |
| GET    | `/api/test-plans`       | List configured load test plans          |
| GET    | `/api/test-plans/:id`   | Retrieve plan details                    |
| POST   | `/api/test-plans`       | Create a new plan draft                  |
| PATCH  | `/api/test-plans/:id/status` | Update plan workflow status        |
| GET    | `/api/runs`             | List historical and active runs          |
| GET    | `/api/runs/:id`         | Retrieve run timeline and metadata       |
| POST   | `/api/runs`             | Schedule a new ad-hoc run                |
| GET    | `/api/reports`          | Browse available test reports            |
| GET    | `/api/reports/:id`      | Retrieve a report with insights          |

For production use, persist the data layer to a database, swap the static analytics with
real telemetry, and secure the endpoints with your identity strategy.
