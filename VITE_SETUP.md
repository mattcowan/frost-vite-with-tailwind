# Vite & Tailwind Setup for Frost Child Theme

This theme now includes a production-ready Vite & Tailwind CSS v4 integration with hot module replacement (HMR) support.

## 📁 File Structure

```
frost_child/
├── config.php                    # Vite dev server configuration
├── functions.php                 # Main theme file with environment detection
├── functions/
│   ├── dev-assets.php           # Dev server detection & HMR
│   └── prod-assets.php          # Production manifest-based asset loading
├── src/
│   ├── css/main.css             # Main CSS entry (Tailwind imports)
│   └── js/main.js               # Main JS entry
├── dist/                        # Build output (gitignored)
├── package.json                 # Dependencies and build scripts
├── vite.config.js              # Vite configuration
├── tailwind.config.js          # Tailwind CSS configuration
├── postcss.config.js           # PostCSS configuration
└── verify-build.js             # Build verification script
```

## 🚀 Getting Started

### 1. Install Dependencies

```bash
npm install
```

### 2. Development Mode (with HMR)

```bash
npm run dev
```

This starts the Vite dev server at `http://127.0.0.1:3000` with hot module replacement. The theme will automatically detect the dev server and load assets from it.

**Features:**
- ✅ Instant CSS updates without page reload
- ✅ Fast JavaScript HMR
- ✅ Auto-fallback to production build if server not running
- ✅ Admin notice when dev mode is active

### 3. Production Build

```bash
npm run build
```

Outputs optimized, minified assets to `dist/` with:
- Cache-busted filenames (e.g., `main.DtwKV1wl.js`)
- Minified CSS and JS
- Console statements removed (except error/warn)
- Vite manifest for WordPress integration

### 4. Verify Build

```bash
npm run verify
```

Validates the build output for security and completeness.

### 5. Watch Mode

```bash
npm run watch
```

Builds and watches for changes (no HMR, but no dev server needed).

## 🔧 Configuration

### Dev Server Settings

Edit [config.php](config.php) or use filters/constants:

**Option 1: Filters in functions.php**
```php
add_filter('frost_child_vite_dev_host', fn() => '127.0.0.1');
add_filter('frost_child_vite_dev_port', fn() => 3001);
```

**Option 2: Constants in wp-config.php**
```php
define('VITE_DEV_SERVER_HOST', 'localhost');
define('VITE_DEV_SERVER_PORT', 3001);
```

### Tailwind Content Paths

Edit [tailwind.config.js](tailwind.config.js) to scan your files:

```javascript
content: [
  './**/*.php',
  './src/**/*.{js,jsx,ts,tsx}',
  '../frost/**/*.php', // Parent theme
],
```

## 🎯 How It Works

### Development Mode Detection

The theme automatically enables dev mode when:
- `WP_DEBUG` is true
- `WP_ENVIRONMENT_TYPE` is not 'production'
- Running on localhost/127.0.0.1
- Running on `.local`, `.test`, `.dev` domains
- Custom filter: `frost_child_is_dev_environment`

### Asset Loading Priority

1. **Dev mode enabled** → Check for Vite dev server (ports 3000-3005)
2. **Dev server running** → Load from `http://127.0.0.1:3000` with HMR
3. **Dev server not running** → Load from `dist/` manifest
4. **Production mode** → Always load from `dist/` manifest

### Security Features

- Path traversal protection in manifest validation
- Filename pattern validation (8-char hash)
- DNS lookup caching to prevent performance issues
- Transient caching for dev server detection

## 📝 NPM Scripts

| Command | Description |
|---------|-------------|
| `npm run dev` | Start Vite dev server with HMR |
| `npm run build` | Production build |
| `npm run verify` | Validate build output |
| `npm run build:verify` | Build and verify in one command |
| `npm run watch` | Build in watch mode |
| `npm run preview` | Preview production build |

## 🎨 Using Tailwind

### In CSS

```css
/* src/css/main.css */
.btn-primary {
  background-color: theme(colors.blue.500);
  color: theme(colors.white);
  
  &:hover {
    background-color: theme(colors.blue.700);
  }
}
```

### In PHP/HTML

```php
<button class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">
  Click me
</button>
```

## 🐛 Troubleshooting

### Dev server not detected

1. Ensure `npm run dev` is running
2. Check port isn't blocked by firewall
3. Verify `WP_DEBUG` is true or environment is not production
4. Check browser console for HMR connection errors

### Assets not loading

1. Run `npm run build` to generate production assets
2. Check `dist/.vite/manifest.json` exists
3. Run `npm run verify` to validate build
4. Clear WordPress transient cache

### HMR not working

1. Ensure you're accessing site via localhost or .local domain
2. Check browser console for WebSocket errors
3. Verify Vite dev server is running on correct port
4. Try clearing browser cache and hard reload

## 🔗 Key Improvements Over Source

1. **Auto-fallback**: Graceful degradation if dev server stops
2. **Multi-port detection**: Checks ports 3000-3005 automatically
3. **Smart caching**: WordPress transients for performance
4. **Security hardening**: Path validation, DNS caching
5. **Better DX**: Admin notices, clear error messages
6. **IPv6 support**: Handles both IPv4 and IPv6 properly

## 📚 File References

- Dev assets: [functions/dev-assets.php](functions/dev-assets.php)
- Prod assets: [functions/prod-assets.php](functions/prod-assets.php)
- Main functions: [functions.php](functions.php)
- Vite config: [vite.config.js](vite.config.js)
- Tailwind config: [tailwind.config.js](tailwind.config.js)
