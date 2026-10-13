// sitemap.xml for search engines (GHL generated one automatically). Lists every page except 404.
const pages = import.meta.glob('./*.astro');

export function GET({ site }) {
  const paths = Object.keys(pages)
    .map((f) => f.replace('./', '').replace('.astro', ''))
    .filter((p) => p !== '404')
    .map((p) => (p === 'index' ? '/' : `/${p}`))
    .sort();
  const urls = paths.map((p) => `  <url><loc>${new URL(p, site).href}</loc></url>`).join('\n');
  return new Response(
    `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls}\n</urlset>\n`,
    { headers: { 'Content-Type': 'application/xml' } },
  );
}
