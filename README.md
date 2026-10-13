# FordFit Personal Training website

The fordfitpt.com site, rebuilt from GoHighLevel as a static Astro site. Hosted on Hostinger.

```
npm install
npm run dev      # http://localhost:4321
npm run build    # outputs dist/
```

## Deploying to Hostinger

Every push to `main` runs `.github/workflows/deploy.yml`, which builds the site and puts the
finished files on the `hostinger` branch. Hostinger serves that branch.

One-time setup in hPanel → Websites → fordfitpt.com → Advanced → **Git**:

- Repository: `git@github.com:KaizenAdam/fordfitpt-site.git` (private, so add the SSH key
  hPanel shows you as a read-only **Deploy key** in GitHub → repo Settings → Deploy keys)
- Branch: `hostinger`
- Directory: leave empty (deploys into `public_html`, which must be empty first)
- Turn on **Auto deployment** and paste the webhook URL hPanel gives you into GitHub →
  repo Settings → Webhooks (content type `application/json`, push events)

`public/.htaccess` serves `/about-me` from `about-me.html`, forces HTTPS, redirects
`www.` to `fordfitpt.com`, and sends `/home` to `/`.

## Where things live

- Pages: `src/pages/` (file names match the old GHL addresses)
- Shared sections (gym banner, price table, logo strip, reviews band, app promo, story gallery,
  photo swipe, pixel photo): `src/components/`
- Shared details (phone, email, social links, service pages): `src/site.js`
- Toby's story text (About and Online Coaching galleries): `src/data/story.js`
- Styles shared across pages: `src/styles/global.css`; section styles live in each component

## Still on GoHighLevel

These are GHL embeds and stop working if the GHL account is closed:

- Review widgets (`ReviewWidget.astro`, widget IDs set on each page)
- Start Your Journey form "FORDFIT 1" (`start-your-journey-page.astro`)
- Home page "7 day free trial" popup form (`AppPopup.astro`)

Page URLs match the old GHL paths (`/about-me`, `/online-coaching`, …) so existing links and
search rankings carry over.
