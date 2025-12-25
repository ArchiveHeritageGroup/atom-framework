const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');
const http = require('http');

async function generateThumbnail(glbPath, outputPath, width = 512, height = 512) {
    const absPath = path.resolve(glbPath);
    
    if (!fs.existsSync(absPath)) {
        throw new Error(`File not found: ${absPath}`);
    }
    
    // Start a simple HTTP server to serve the GLB file
    const port = 9876 + Math.floor(Math.random() * 1000);
    const server = http.createServer((req, res) => {
        if (req.url === '/model.glb') {
            const stat = fs.statSync(absPath);
            res.writeHead(200, {
                'Content-Type': 'model/gltf-binary',
                'Content-Length': stat.size,
                'Access-Control-Allow-Origin': '*'
            });
            fs.createReadStream(absPath).pipe(res);
        } else {
            res.writeHead(404);
            res.end();
        }
    });
    
    await new Promise(resolve => server.listen(port, resolve));
    console.log(`Temp server on port ${port}`);
    
    const browser = await puppeteer.launch({
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-gpu',
            '--use-gl=swiftshader',
            '--enable-webgl'
        ]
    });
    
    try {
        const page = await browser.newPage();
        await page.setViewport({ width, height });
        
        const html = `
        <!DOCTYPE html>
        <html>
        <head>
            <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js"></script>
            <style>
                * { margin: 0; padding: 0; }
                body { width: ${width}px; height: ${height}px; overflow: hidden; }
                model-viewer {
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                }
            </style>
        </head>
        <body>
            <model-viewer
                id="viewer"
                src="http://127.0.0.1:${port}/model.glb"
                camera-orbit="45deg 55deg auto"
                field-of-view="auto"
                auto-rotate
                rotation-per-second="0"
            ></model-viewer>
            <script>
                const viewer = document.getElementById('viewer');
                viewer.addEventListener('load', () => {
                    console.log('Model loaded successfully');
                    setTimeout(() => { window.modelLoaded = true; }, 1000);
                });
                viewer.addEventListener('error', (e) => {
                    console.error('Model error:', e.detail);
                    window.modelError = e.detail;
                });
            </script>
        </body>
        </html>`;
        
        await page.setContent(html, { waitUntil: 'networkidle0', timeout: 60000 });
        
        // Wait for model to load with better error handling
        const result = await page.evaluate(() => {
            return new Promise((resolve) => {
                const checkLoaded = setInterval(() => {
                    if (window.modelLoaded) {
                        clearInterval(checkLoaded);
                        resolve({ success: true });
                    }
                    if (window.modelError) {
                        clearInterval(checkLoaded);
                        resolve({ success: false, error: window.modelError });
                    }
                }, 100);
                
                // Timeout after 30 seconds
                setTimeout(() => {
                    clearInterval(checkLoaded);
                    resolve({ success: false, error: 'timeout' });
                }, 30000);
            });
        });
        
        if (result.success) {
            console.log('Model rendered successfully');
            await new Promise(r => setTimeout(r, 500)); // Extra render time
        } else {
            console.log('Model load issue:', result.error);
        }
        
        await page.screenshot({ path: outputPath, type: 'png' });
        console.log(`Thumbnail saved to: ${outputPath}`);
        
    } finally {
        await browser.close();
        server.close();
    }
    
    return outputPath;
}

// CLI
const args = process.argv.slice(2);
if (args.length < 2) {
    console.log('Usage: node generate-thumbnail.js <input.glb> <output.png> [width] [height]');
    process.exit(1);
}

const [input, output, width, height] = args;

generateThumbnail(input, output, parseInt(width) || 512, parseInt(height) || 512)
    .then(() => process.exit(0))
    .catch(err => {
        console.error('Error:', err.message);
        process.exit(1);
    });
