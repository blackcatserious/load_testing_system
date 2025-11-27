const styles = `
  :root {
    color-scheme: dark;
    font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    --bg: radial-gradient(circle at top, #151b2f 0%, #070b16 45%, #020309 100%);
    --panel: rgba(13, 20, 35, 0.85);
    --border: rgba(96, 194, 255, 0.45);
    --accent: #60c2ff;
    --accent-strong: #23a7ff;
    --text: #f5f8ff;
    --muted: rgba(245, 248, 255, 0.65);
  }

  * {
    box-sizing: border-box;
  }

  body {
    margin: 0;
    min-height: 100vh;
    background: var(--bg);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 16px;
  }

  .shell {
    width: min(1080px, 100%);
    border-radius: 24px;
    padding: 48px;
    border: 1px solid var(--border);
    background: linear-gradient(145deg, rgba(12, 22, 36, 0.92), rgba(9, 15, 27, 0.86));
    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.55);
    position: relative;
    overflow: hidden;
  }

  .shell::after {
    content: '';
    position: absolute;
    inset: -40% auto auto -40%;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle, rgba(35, 167, 255, 0.45) 0%, rgba(35, 167, 255, 0) 65%);
    opacity: 0.6;
    filter: blur(0.5px);
    pointer-events: none;
  }

  .grid {
    display: grid;
    gap: 32px;
  }

  header {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  header .tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(35, 167, 255, 0.12);
    border: 1px solid rgba(96, 194, 255, 0.38);
    color: var(--accent);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 12px;
  }

  h1 {
    font-size: clamp(42px, 5vw, 56px);
    margin: 0;
    font-weight: 700;
    line-height: 1.08;
  }

  p.lede {
    margin: 0;
    max-width: 640px;
    color: var(--muted);
    font-size: 18px;
    line-height: 1.6;
  }

  .panels {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
  }

  .panel {
    padding: 22px 24px;
    border-radius: 18px;
    border: 1px solid rgba(96, 194, 255, 0.25);
    background: linear-gradient(160deg, rgba(14, 23, 37, 0.78), rgba(7, 10, 18, 0.9));
    position: relative;
    overflow: hidden;
  }

  .panel h2 {
    margin: 0 0 12px;
    font-size: 18px;
    font-weight: 600;
    color: var(--accent);
  }

  .panel p {
    margin: 0;
    color: var(--muted);
    font-size: 15px;
    line-height: 1.5;
  }

  .panel strong {
    color: var(--text);
    font-weight: 600;
  }

  footer {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid rgba(96, 194, 255, 0.15);
    padding-top: 24px;
    color: rgba(245, 248, 255, 0.5);
    font-size: 13px;
  }

  footer a {
    color: var(--accent);
    text-decoration: none;
    font-weight: 600;
  }

  @media (max-width: 720px) {
    .shell {
      padding: 32px 20px;
    }

    header {
      gap: 14px;
    }
  }
`;

const heroIllustration = `
  <svg width="420" height="320" viewBox="0 0 420 320" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Domain command center visualization">
    <defs>
      <linearGradient id="grad" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#60c2ff" stop-opacity="0.8" />
        <stop offset="100%" stop-color="#1a5bff" stop-opacity="0" />
      </linearGradient>
      <radialGradient id="pulse" cx="50%" cy="50%" r="65%">
        <stop offset="0%" stop-color="#23a7ff" stop-opacity="0.9" />
        <stop offset="100%" stop-color="#23a7ff" stop-opacity="0" />
      </radialGradient>
    </defs>
    <rect x="14" y="32" width="392" height="224" rx="24" fill="url(#grad)" fill-opacity="0.16" stroke="#60c2ff" stroke-opacity="0.2" />
    <rect x="62" y="74" width="296" height="140" rx="16" fill="#060b16" stroke="#60c2ff" stroke-opacity="0.35" />
    <path d="M90 164C146 140 170 210 226 190C274 170 282 96 330 112" stroke="#60c2ff" stroke-opacity="0.55" stroke-width="3" stroke-linecap="round" />
    <circle cx="170" cy="148" r="18" fill="url(#pulse)" />
    <circle cx="242" cy="190" r="12" fill="url(#pulse)" />
    <circle cx="308" cy="126" r="10" fill="url(#pulse)" />
    <g filter="url(#shadow)">
      <rect x="96" y="96" width="84" height="28" rx="10" fill="#0a1322" stroke="#60c2ff" stroke-opacity="0.4" />
      <rect x="96" y="132" width="54" height="16" rx="6" fill="#0a1322" stroke="#23a7ff" stroke-opacity="0.4" />
    </g>
  </svg>
`;

export const defaultLandingPageHtml = `<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Domain Command Center</title>
    <style>${styles}</style>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  </head>
  <body>
    <main class="shell" role="main">
      <div class="grid">
        <header>
          <span class="tag">Live domain orchestration</span>
          <h1>Your domain command center is preparing telemetry</h1>
          <p class="lede">
            The control plane is online and awaiting the latest orchestration build. Deploy the refreshed frontend bundle to unlock
            real-time dashboards, proxy intelligence, and test run analytics across your attack surfaces.
          </p>
        </header>

        <div class="panels" role="presentation">
          <section class="panel">
            <h2>1. Verify orchestration endpoint</h2>
            <p>
              Confirm the PHP orchestration layer is reachable from this host. All <strong>*/api/*.php</strong> routes should return
              JSON with <strong>success: true</strong> before deploying the UI bundle.
            </p>
          </section>
          <section class="panel">
            <h2>2. Build the React dashboard</h2>
            <p>
              From <strong>frontend_src</strong> run <strong>npm install</strong> and <strong>npm run build</strong>. Upload the
              generated <strong>dist</strong> directory so the command center interface loads automatically.
            </p>
          </section>
          <section class="panel">
            <h2>3. Redeploy &amp; verify</h2>
            <p>
              Restart the Node service. When the bundle is present this page will be replaced with the live dashboard without any
              additional configuration changes.
            </p>
          </section>
        </div>

        <figure aria-hidden="true" style="margin:0;display:flex;justify-content:center;filter:drop-shadow(0 24px 48px rgba(35, 167, 255, 0.25));">
          ${heroIllustration}
        </figure>

        <footer>
          <span>Need to deploy? Point your domain to this host and ensure ports 80/443 proxy to the Node runtime.</span>
          <a href="/api/dashboard" aria-label="Open live API diagnostics">View API diagnostics</a>
        </footer>
      </div>
    </main>
  </body>
</html>`;

export default defaultLandingPageHtml;
