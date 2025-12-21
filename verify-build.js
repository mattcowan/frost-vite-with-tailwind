/**
 * Build verification script
 * Checks that the Vite manifest includes required compiled assets
 * Run with: node verify-build.js
 */

import { readFileSync, existsSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join, resolve, normalize, sep } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const manifestPath = join(__dirname, 'dist', '.vite', 'manifest.json');
const requiredAssets = ['src/js/main.js', 'src/css/main.css'];
const distDir = resolve(__dirname, 'dist');

/**
 * Validate that a file path is safe and within the dist directory
 * @param {string} filePath - The file path to validate
 * @returns {boolean} - True if safe, false otherwise
 */
function isPathSafe(filePath) {
    const normalized = normalize(filePath);
    const resolved = resolve(distDir, normalized);
    return resolved.startsWith(distDir + sep) || resolved === distDir;
}

console.log('🔍 Verifying Vite build...\n');

// Check if manifest exists
if (!existsSync(manifestPath)) {
    console.error('❌ Manifest file not found at:', manifestPath);
    console.error('   Run "npm run build" first.\n');
    process.exit(1);
}

// Read and parse manifest
let manifest;
try {
    const manifestContent = readFileSync(manifestPath, 'utf8');
    manifest = JSON.parse(manifestContent);
} catch (error) {
    console.error('❌ Failed to parse manifest.json:', error.message, '\n');
    process.exit(1);
}

// Check required assets and validate paths
let allValid = true;
for (const asset of requiredAssets) {
    if (!manifest[asset]) {
        console.error(`❌ Missing asset in manifest: ${asset}`);
        allValid = false;
    } else {
        const compiledFile = manifest[asset].file;

        // Validate path for security issues
        if (!isPathSafe(compiledFile)) {
            console.error(`❌ ${asset}`);
            console.error(`   → Invalid path detected: ${compiledFile}`);
            console.error(`   → Path must remain within dist directory\n`);
            allValid = false;
        } else {
            // Verify the compiled file actually exists
            const compiledFilePath = join(__dirname, 'dist', compiledFile);
            if (!existsSync(compiledFilePath)) {
                console.error(`❌ ${asset}`);
                console.error(`   → Compiled file missing: ${compiledFilePath}\n`);
                allValid = false;
            } else {
                console.log(`✅ ${asset}`);
                console.log(`   → ${compiledFile}\n`);
            }
        }
    }
}

// Validate all manifest entries for path security and file existence
console.log('🔍 Validating all manifest entries for path security and file existence...\n');
for (const [key, entry] of Object.entries(manifest)) {
    if (entry.file) {
        // Check for path traversal
        if (!isPathSafe(entry.file)) {
            console.error(`❌ Security issue in manifest entry: ${key}`);
            console.error(`   → Path must remain within dist directory: ${entry.file}\n`);
            allValid = false;
        } else {
            // Verify file exists
            const filePath = join(__dirname, 'dist', entry.file);
            if (!existsSync(filePath)) {
                console.error(`❌ Missing file for manifest entry: ${key}`);
                console.error(`   → File not found: ${filePath}\n`);
                allValid = false;
            }
        }
    }
    // Check CSS files referenced in entries
    if (entry.css && Array.isArray(entry.css)) {
        for (const cssFile of entry.css) {
            // Check for path traversal
            if (!isPathSafe(cssFile)) {
                console.error(`❌ Security issue in CSS reference for: ${key}`);
                console.error(`   → Path must remain within dist directory: ${cssFile}\n`);
                allValid = false;
            } else {
                // Verify CSS file exists
                const cssFilePath = join(__dirname, 'dist', cssFile);
                if (!existsSync(cssFilePath)) {
                    console.error(`❌ Missing CSS file referenced in: ${key}`);
                    console.error(`   → File not found: ${cssFilePath}\n`);
                    allValid = false;
                }
            }
        }
    }
}

// Summary
if (allValid) {
    console.log('✅ All required assets are present in manifest!\n');
    process.exit(0);
} else {
    console.error('❌ Build verification failed.\n');
    process.exit(1);
}
