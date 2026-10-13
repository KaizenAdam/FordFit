import { defineConfig } from 'astro/config';

export default defineConfig({
  site: 'https://fordfitpt.com',
  // Keep GHL's URLs (e.g. /about-me, not /about-me/) so links and rankings carry over.
  trailingSlash: 'never',
  build: { format: 'file' },
});
