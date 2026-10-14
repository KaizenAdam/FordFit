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

## Forms (Resend)

Start Your Journey and the home page "7 day free trial" popup post to `public/api/contact.php`,
which emails Toby through Resend (same setup as the tinyFIRES site) and sends the enquirer an
auto-reply. Settings live in **`fordfit-config.php` in the hosting account's home folder**, one
level above `public_html`, so the web can't read it and deploys never touch it. This repository
is public: never commit the Resend key.

One-time setup:

1. Resend → Domains → add `send.fordfitpt.com` and add the DNS records it lists wherever
   fordfitpt.com's DNS is managed. Wait until it shows Verified.
2. Resend → API Keys → create a key with "Sending access" for that domain only.
3. hPanel → File Manager → the home folder (the one containing `public_html`) → create
   `fordfit-config.php` from `public/api/config.example.php` and paste the key.
4. To test without emailing Toby, put your own address in `TEST_RECIPIENTS` in `contact.php`,
   deploy, submit both forms, then set it back to `[]`.

## Reviews

The review sections are built from `src/data/reviews.js`: Toby's 50 written Google reviews,
copied from the GHL widgets on 14 Oct 2026. They don't update by themselves. To add a review,
copy it from Google into the top of that list and bump `REVIEW_COUNT` (also update the
`reviewCount` in `src/layouts/Base.astro`, which feeds the stars in Google results).
