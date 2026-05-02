# DeeThrifts Edge Tracking Sync Extension

This extension syncs tracking + delivery status from MS Royal to your store.

## What it does
- Runs hourly in background automatically.
- Manual sync from popup works even if another tab is active.
- Saves API URL and token in extension storage.
- Pushes rows to `api.php` using `type=sync_tracking_batch`.
- If portal row is delivered, backend moves order to Delivered and runs cleanup (product image/file removal + product deletion flow).

## Load in Edge
1. Open `edge://extensions`
2. Enable `Developer mode`
3. Click `Load unpacked`
4. Select: `edge-tracking-sync-extension`

## Defaults
- API URL default: `https://deethrifts.store/api.php`
- Sync token default: `0987654321ghlopin`
