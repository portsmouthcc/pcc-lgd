# PCC OSM Proxy

A Drupal module that proxies OpenStreetMap tile requests through your own
server, preventing the end-user's IP address from being disclosed to
OpenStreetMap or any other third party. This satisfies the GDPR
data-minimisation principle for embedded maps.

## How it works

```
Browser  ──GET /osm-tile/standard/10/511/340──►  Drupal controller
                                                        │
                                          (cache hit?)  │
                                                        ▼
                                              TileFetcher service
                                                        │
                                    (only if not cached)│
                                                        ▼
                                          tile.openstreetmap.org
                                          (no user IP, no cookies)
```

1. Leaflet is configured to request tiles from `/osm-tile/{style}/{z}/{x}/{y}`
   on your own domain.
2. The Drupal controller validates the request and delegates to `TileFetcher`.
3. `TileFetcher` checks Drupal's cache. On a cache miss it fetches the tile
   from OpenStreetMap using a server-side HTTP request — **the user's browser
   never contacts OpenStreetMap**.
4. The tile is cached for `tile_cache_max_age` seconds (default 1 hour) and
   returned to the browser with appropriate `Cache-Control` headers.

## Requirements

- Drupal 10 or 11
- [Leaflet](https://www.drupal.org/project/leaflet) module

## Installation

```bash
cp -r pcc_osm_proxy web/modules/custom/
drush en pcc_osm_proxy -y
drush cr
```

## Configuration

Settings are stored in `pcc_osm_proxy.settings` (CMI).

| Key | Default | Description |
|-----|---------|-------------|
| `tile_cache_max_age` | `3600` | Seconds to cache each tile server-side |
| `allowed_styles.standard.url` | OSM standard URL | Upstream tile URL template |
| `user_agent` | `PccOsmProxy/1.0 (…)` | User-Agent sent to OSM |

**Important**: OpenStreetMap's
[tile usage policy](https://operations.osmfoundation.org/policies/tiles/) requires
a descriptive User-Agent containing a link to your site. Update `user_agent`
in `config/install/pcc_osm_proxy.settings.yml` before enabling the module, or
override it in `settings.php`:

```php
$config['pcc_osm_proxy.settings']['user_agent'] =
  'MyOrg Map Proxy/1.0 (https://mysite.example.com/contact)';
```

### Adding extra tile styles (e.g. CyclOSM)

Add an entry under `allowed_styles` in `pcc_osm_proxy.settings`:

```yaml
allowed_styles:
  standard:
    url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
  cyclosm:
    url: 'https://tile.waymarkedtrails.org/cycling/{z}/{x}/{y}.png'
```

Then add a matching `LeafletTileLayerPlugin` in
`src/Plugin/LeafletTileLayerPlugin/` following the pattern of
`PccOsmProxyStandard.php`, changing the style key in the URL path.

## Using the tile layer provider

1. Go to any Leaflet-powered field formatter or view and select
   **"OpenStreetMap (privacy proxy)"** as the map tile provider.
2. Tiles will now be fetched server-side.

## Privacy & GDPR notes

- The user's IP address, cookies, and browser headers are **never forwarded**
  to the upstream tile server.
- Tiles are cached on the server to reduce upstream requests and improve
  performance.
- The `Referrer-Policy: no-referrer` header is set on tile responses as an
  additional measure.
- Update your Privacy Policy to note that map tiles are served from your own
  infrastructure.

## Troubleshooting

| Symptom | Likely cause |
|---------|-------------|
| 404 on tile requests | Style key in URL doesn't match `allowed_styles` config |
| 503 on tile requests | Upstream OSM server unreachable from your host |
| Blank map in browser | Check browser console for CORS errors |
| OSM blocks your server | Update `user_agent` to include a valid contact URL |
