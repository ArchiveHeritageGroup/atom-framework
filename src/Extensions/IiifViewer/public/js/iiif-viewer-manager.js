/**
 * IIIF Viewer Manager
 * 
 * Manages multiple viewer types and switches between them:
 * - OpenSeadragon for IIIF images
 * - Mirador 3 for rich IIIF viewing
 * - PDF.js for PDF documents
 * - Model Viewer for 3D models
 * - Annotorious for annotations
 * 
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */

export class IiifViewerManager {
    constructor(viewerId, options = {}) {
        this.viewerId = viewerId;
        this.options = {
            objectId: null,
            manifestUrl: null,
            baseUrl: 'https://archives.theahg.co.za',
            cantaloupeUrl: 'https://archives.theahg.co.za/iiif/2',
            frameworkPath: '/atom-framework/src/Extensions/IiifViewer',
            defaultViewer: 'openseadragon',
            flags: {},
            osdConfig: {},
            miradorConfig: {},
            embedded: false,
            ...options
        };
        
        this.currentViewer = null;
        this.osdViewer = null;
        this.miradorInstance = null;
        this.pdfDoc = null;
        this.pdfPage = 1;
        this.pdfScale = 1.5;
        this.annotorious = null;
        this.annotations = [];
        
        this.loaded = {
            osd: false,
            mirador: false,
            pdfjs: false,
            modelViewer: false,
            annotorious: false
        };
    }
    
    async init() {
        // Store preference
        const savedViewer = localStorage.getItem('iiif_viewer_pref');
        this.currentViewer = savedViewer || this.options.defaultViewer;

        // Bind events
        this.bindEvents();

        // Load initial viewer
        await this.showViewer(this.currentViewer);

        // Load annotations if enabled
        if (this.options.flags.enableAnnotations) {
            await this.loadAnnotations();
        }

        // Store instance globally for callbacks
        if (typeof window !== 'undefined') {
            window.iiifViewerManager = this;
        }
    }
    
    bindEvents() {
        const vid = this.viewerId;
        
        // Viewer toggle buttons
        this.on(`btn-osd-${vid}`, 'click', () => this.showViewer('openseadragon'));
        this.on(`btn-mirador-${vid}`, 'click', () => this.showViewer('mirador'));
        this.on(`btn-pdf-${vid}`, 'click', () => this.showViewer('pdfjs'));
        this.on(`btn-3d-${vid}`, 'click', () => this.showViewer('model-viewer'));
        this.on(`btn-av-${vid}`, 'click', () => this.showViewer('av'));
        
        // Close mirador
        this.on(`close-mirador-${vid}`, 'click', () => this.showViewer('openseadragon'));
        
        // Control buttons
        this.on(`btn-fullscreen-${vid}`, 'click', () => this.toggleFullscreen());
        this.on(`btn-newwin-${vid}`, 'click', () => this.openInNewWindow());
        this.on(`btn-download-${vid}`, 'click', () => this.downloadImage());
        this.on(`btn-annotations-${vid}`, 'click', () => this.toggleAnnotations());
        this.on(`btn-manifest-${vid}`, 'click', () => this.copyManifestUrl());
        
        // PDF controls
        this.on(`pdf-prev-${vid}`, 'click', () => this.pdfPrevPage());
        this.on(`pdf-next-${vid}`, 'click', () => this.pdfNextPage());
        this.on(`pdf-zoom-in-${vid}`, 'click', () => this.pdfZoom(0.25));
        this.on(`pdf-zoom-out-${vid}`, 'click', () => this.pdfZoom(-0.25));
        
        // Thumbnail strip
        const thumbs = document.querySelectorAll(`#thumbs-${vid} .thumb-item`);
        thumbs.forEach(thumb => {
            thumb.addEventListener('click', () => {
                const index = parseInt(thumb.dataset.index);
                this.goToPage(index);
                
                // Update active state
                thumbs.forEach(t => t.classList.remove('active'));
                thumb.classList.add('active');
            });
        });
    }
    
    on(elementId, event, handler) {
        const el = document.getElementById(elementId);
        if (el) {
            el.addEventListener(event, handler);
        }
    }
    
    // ========================================================================
    // Viewer Switching
    // ========================================================================
    
    async showViewer(viewerType) {
        const vid = this.viewerId;
        
        // Hide all viewers
        this.hideElement(`osd-${vid}`);
        this.hideElement(`mirador-wrapper-${vid}`);
        this.hideElement(`pdf-wrapper-${vid}`);
        this.hideElement(`model-wrapper-${vid}`);
        this.hideElement(`av-wrapper-${vid}`);
        
        // Update button states
        this.updateButtonStates(viewerType);
        
        // Show selected viewer
        switch (viewerType) {
            case 'openseadragon':
                await this.initOpenSeadragon();
                this.showElement(`osd-${vid}`);
                break;
                
            case 'mirador':
                await this.initMirador();
                this.showElement(`mirador-wrapper-${vid}`);
                break;
                
            case 'pdfjs':
                await this.initPdfJs();
                this.showElement(`pdf-wrapper-${vid}`);
                break;
                
            case 'model-viewer':
                await this.initModelViewer();
                this.showElement(`model-wrapper-${vid}`);
                break;
                
            case 'av':
                this.showElement(`av-wrapper-${vid}`);
                break;
        }
        
        this.currentViewer = viewerType;
        localStorage.setItem('iiif_viewer_pref', viewerType);
        
        // Re-init annotorious for OSD
        if (viewerType === 'openseadragon' && this.options.flags.enableAnnotations) {
            await this.initAnnotorious();
        }
    }
    
    updateButtonStates(activeViewer) {
        const vid = this.viewerId;
        const buttons = {
            'openseadragon': `btn-osd-${vid}`,
            'mirador': `btn-mirador-${vid}`,
            'pdfjs': `btn-pdf-${vid}`,
            'model-viewer': `btn-3d-${vid}`,
            'av': `btn-av-${vid}`
        };
        
        Object.entries(buttons).forEach(([viewer, btnId]) => {
            const btn = document.getElementById(btnId);
            if (btn) {
                if (viewer === activeViewer) {
                    btn.classList.remove('btn-outline-primary');
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                }
            }
        });
    }
    
    // ========================================================================
    // OpenSeadragon
    // ========================================================================
    
    async initOpenSeadragon() {
        if (this.osdViewer) return;
        
        // Load OSD if not loaded
        if (!window.OpenSeadragon) {
            await this.loadScript('https://cdn.jsdelivr.net/npm/openseadragon@3.1.0/build/openseadragon/openseadragon.min.js');
        }
        
        const vid = this.viewerId;
        const containerId = `osd-${vid}`;
        
        // Fetch manifest to get tile sources
        const manifest = await this.fetchManifest();
        const tileSources = this.extractTileSources(manifest);
        
        const config = {
            id: containerId,
            prefixUrl: 'https://cdn.jsdelivr.net/npm/openseadragon@3.1.0/build/openseadragon/images/',
            tileSources: tileSources,
            showNavigator: true,
            navigatorPosition: 'BOTTOM_RIGHT',
            showRotationControl: true,
            showFlipControl: true,
            gestureSettingsMouse: { scrollToZoom: true },
            ...this.options.osdConfig
        };
        
        // Multi-image mode
        if (tileSources.length > 1) {
            config.sequenceMode = true;
            config.showReferenceStrip = true;
            config.referenceStripScroll = 'horizontal';
        }
        
        this.osdViewer = OpenSeadragon(config);
        this.loaded.osd = true;
    }
    
    extractTileSources(manifest) {
        const tileSources = [];
        
        if (!manifest || !manifest.items) return tileSources;
        
        manifest.items.forEach(canvas => {
            if (canvas.items && canvas.items[0] && canvas.items[0].items) {
                canvas.items[0].items.forEach(annotation => {
                    if (annotation.body && annotation.body.service) {
                        const service = Array.isArray(annotation.body.service) 
                            ? annotation.body.service[0] 
                            : annotation.body.service;
                        
                        if (service && service.id) {
                            tileSources.push(service.id + '/info.json');
                        }
                    }
                });
            }
        });
        
        return tileSources;
    }
    
    // ========================================================================
    // Mirador 3
    // ========================================================================
    
    async initMirador() {
        if (this.miradorInstance) return;
        
        const vid = this.viewerId;
        const path = this.options.frameworkPath;
        
        // Load Mirador CSS
        if (!document.getElementById('mirador-css')) {
            const link = document.createElement('link');
            link.id = 'mirador-css';
            link.rel = 'stylesheet';
            link.href = `${path}/public/viewers/mirador/mirador.min.css`;
            document.head.appendChild(link);
        }
        
        // Load Mirador JS
        if (!window.Mirador) {
            await this.loadScript(`${path}/public/viewers/mirador/mirador.min.js`);
        }
        
        this.miradorInstance = Mirador.viewer({
            id: `mirador-${vid}`,
            windows: [{ manifestId: this.options.manifestUrl }],
            window: {
                allowClose: false,
                allowMaximize: true,
                defaultSideBarPanel: 'info',
                sideBarOpenByDefault: false,
                panels: {
                    info: true,
                    attribution: true,
                    canvas: true,
                    annotations: true,
                    search: true
                }
            },
            workspace: {
                showZoomControls: true
            },
            ...this.options.miradorConfig
        });
        
        this.loaded.mirador = true;
    }
    
    // ========================================================================
    // PDF.js
    // ========================================================================

    async initPdfJs() {
        if (this.pdfDoc) return;

        // Load PDF.js
        if (!window.pdfjsLib) {
            await this.loadScript('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js');
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }

        // Use direct PDF URL if provided (for PII redaction), otherwise get from manifest
        let pdfUrl = this.options.flags.pdfUrl || null;

        if (!pdfUrl) {
            // Get PDF URL from manifest
            const manifest = await this.fetchManifest();

            if (manifest && manifest.items) {
                for (const canvas of manifest.items) {
                    if (canvas.rendering) {
                        const rendering = Array.isArray(canvas.rendering) ? canvas.rendering[0] : canvas.rendering;
                        if (rendering.format === 'application/pdf') {
                            pdfUrl = rendering.id;
                            break;
                        }
                    }
                }
            }
        }

        if (!pdfUrl) return;

        const loadingTask = pdfjsLib.getDocument(pdfUrl);
        this.pdfDoc = await loadingTask.promise;

        this.pdfPage = 1;
        await this.renderPdfPage();

        // Initialize text selection for redaction if user is authenticated
        if (this.options.flags.enableRedaction) {
            this.initPdfTextSelection();
        }

        this.loaded.pdfjs = true;
    }

    async renderPdfPage() {
        if (!this.pdfDoc) return;

        const vid = this.viewerId;
        const page = await this.pdfDoc.getPage(this.pdfPage);
        const viewport = page.getViewport({ scale: this.pdfScale });

        const canvas = document.getElementById(`pdf-canvas-${vid}`);
        const context = canvas.getContext('2d');

        canvas.height = viewport.height;
        canvas.width = viewport.width;

        await page.render({
            canvasContext: context,
            viewport: viewport
        }).promise;

        // Render text layer for selection
        if (this.options.flags.enableRedaction) {
            await this.renderPdfTextLayer(page, viewport);
        }

        // Update page display
        const pageDisplay = document.getElementById(`pdf-page-${vid}`);
        if (pageDisplay) {
            pageDisplay.textContent = `${this.pdfPage} / ${this.pdfDoc.numPages}`;
        }
    }

    /**
     * Render text layer over canvas for text selection
     */
    async renderPdfTextLayer(page, viewport) {
        const vid = this.viewerId;
        const canvas = document.getElementById(`pdf-canvas-${vid}`);

        // Get or create text layer container
        let textLayer = document.getElementById(`pdf-text-layer-${vid}`);
        if (!textLayer) {
            textLayer = document.createElement('div');
            textLayer.id = `pdf-text-layer-${vid}`;
            textLayer.className = 'pdf-text-layer';
            canvas.parentNode.insertBefore(textLayer, canvas.nextSibling);
        }

        // Clear existing content
        textLayer.innerHTML = '';

        // Position text layer over canvas
        textLayer.style.cssText = `
            position: absolute;
            left: ${canvas.offsetLeft}px;
            top: ${canvas.offsetTop}px;
            width: ${viewport.width}px;
            height: ${viewport.height}px;
            overflow: hidden;
            opacity: 0.2;
            line-height: 1.0;
            pointer-events: auto;
        `;

        // Get text content
        const textContent = await page.getTextContent();

        // Render text items
        pdfjsLib.renderTextLayer({
            textContent: textContent,
            container: textLayer,
            viewport: viewport,
            textDivs: []
        });
    }

    /**
     * Initialize text selection handler for PDF redaction
     */
    initPdfTextSelection() {
        const vid = this.viewerId;
        const wrapper = document.getElementById(`pdf-wrapper-${vid}`);

        if (!wrapper) return;

        // Create redaction button (hidden by default)
        let redactBtn = document.getElementById(`pdf-redact-btn-${vid}`);
        if (!redactBtn) {
            redactBtn = document.createElement('button');
            redactBtn.id = `pdf-redact-btn-${vid}`;
            redactBtn.className = 'btn btn-danger btn-sm pdf-redact-btn';
            redactBtn.innerHTML = '<i class="fas fa-eraser"></i> Redact Selected Text';
            redactBtn.style.cssText = `
                display: none;
                position: absolute;
                z-index: 1000;
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            `;
            wrapper.appendChild(redactBtn);

            // Handle redact button click
            redactBtn.addEventListener('click', () => this.handlePdfRedaction());
        }

        // Create redacted terms panel
        let termsPanel = document.getElementById(`pdf-terms-panel-${vid}`);
        if (!termsPanel) {
            termsPanel = document.createElement('div');
            termsPanel.id = `pdf-terms-panel-${vid}`;
            termsPanel.className = 'pdf-terms-panel';
            termsPanel.innerHTML = `
                <div class="card mt-2">
                    <div class="card-header py-1 px-2 d-flex justify-content-between align-items-center">
                        <small><strong>Redacted Terms</strong></small>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-1" id="pdf-refresh-terms-${vid}">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="card-body p-2" id="pdf-terms-list-${vid}" style="max-height: 150px; overflow-y: auto;">
                        <small class="text-muted">Loading...</small>
                    </div>
                </div>
            `;
            wrapper.appendChild(termsPanel);

            // Bind refresh button
            document.getElementById(`pdf-refresh-terms-${vid}`).addEventListener('click', () => this.loadRedactedTerms());
        }

        // Listen for text selection
        wrapper.addEventListener('mouseup', (e) => this.handlePdfTextSelection(e));

        // Hide button when clicking elsewhere
        document.addEventListener('mousedown', (e) => {
            if (!redactBtn.contains(e.target)) {
                redactBtn.style.display = 'none';
            }
        });

        // Load existing redacted terms
        this.loadRedactedTerms();
    }

    /**
     * Handle text selection in PDF
     */
    handlePdfTextSelection(e) {
        const vid = this.viewerId;
        const selection = window.getSelection();
        const selectedText = selection.toString().trim();

        const redactBtn = document.getElementById(`pdf-redact-btn-${vid}`);

        if (selectedText && selectedText.length > 0 && selectedText.length <= 500) {
            this.selectedPdfText = selectedText;

            // Position button near selection
            const range = selection.getRangeAt(0);
            const rect = range.getBoundingClientRect();
            const wrapper = document.getElementById(`pdf-wrapper-${vid}`);
            const wrapperRect = wrapper.getBoundingClientRect();

            redactBtn.style.display = 'block';
            redactBtn.style.left = `${rect.left - wrapperRect.left}px`;
            redactBtn.style.top = `${rect.bottom - wrapperRect.top + 5}px`;
        } else {
            redactBtn.style.display = 'none';
        }
    }

    /**
     * Handle redaction button click
     */
    async handlePdfRedaction() {
        const vid = this.viewerId;
        const redactBtn = document.getElementById(`pdf-redact-btn-${vid}`);

        if (!this.selectedPdfText || !this.options.objectId) {
            return;
        }

        // Disable button and show loading
        redactBtn.disabled = true;
        redactBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        try {
            const response = await fetch('/privacyAdmin/addManualRedaction', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    object_id: this.options.objectId,
                    text: this.selectedPdfText,
                    redact_all: 'true'
                })
            });

            const result = await response.json();

            if (result.success) {
                // Show success message
                this.showPdfToast('Text marked for redaction. Reload page to see changes.', 'success');

                // Refresh the terms list
                this.loadRedactedTerms();

                // Clear selection
                window.getSelection().removeAllRanges();
            } else {
                this.showPdfToast(result.error || 'Failed to save redaction', 'danger');
            }
        } catch (error) {
            console.error('Redaction failed:', error);
            this.showPdfToast('Failed to save redaction', 'danger');
        }

        // Reset button
        redactBtn.disabled = false;
        redactBtn.innerHTML = '<i class="fas fa-eraser"></i> Redact Selected Text';
        redactBtn.style.display = 'none';
    }

    /**
     * Load redacted terms for the current object
     */
    async loadRedactedTerms() {
        const vid = this.viewerId;
        const termsList = document.getElementById(`pdf-terms-list-${vid}`);

        if (!termsList || !this.options.objectId) return;

        try {
            const response = await fetch(`/privacyAdmin/getRedactedTerms?id=${this.options.objectId}`);
            const result = await response.json();

            if (result.success && result.terms && result.terms.length > 0) {
                termsList.innerHTML = result.terms.map(term => `
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-truncate" style="max-width: 150px;" title="${this.escapeHtml(term.entity_text)}">
                            <span class="badge bg-${term.entity_type === 'MANUAL' ? 'warning' : 'secondary'} me-1">${term.entity_type}</span>
                            ${this.escapeHtml(term.entity_text)}
                        </small>
                        <button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="window.iiifViewerManager.removeRedaction(${term.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `).join('');
            } else {
                termsList.innerHTML = '<small class="text-muted">No redacted terms</small>';
            }
        } catch (error) {
            console.error('Failed to load terms:', error);
            termsList.innerHTML = '<small class="text-danger">Failed to load</small>';
        }
    }

    /**
     * Remove a redaction
     */
    async removeRedaction(entityId) {
        if (!confirm('Remove this redaction?')) return;

        try {
            const response = await fetch('/privacyAdmin/removeManualRedaction', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    entity_id: entityId
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showPdfToast('Redaction removed. Reload page to see changes.', 'success');
                this.loadRedactedTerms();
            } else {
                this.showPdfToast(result.error || 'Failed to remove redaction', 'danger');
            }
        } catch (error) {
            console.error('Failed to remove redaction:', error);
            this.showPdfToast('Failed to remove redaction', 'danger');
        }
    }

    /**
     * Show toast message
     */
    showPdfToast(message, type = 'info') {
        const vid = this.viewerId;
        let toast = document.getElementById(`pdf-toast-${vid}`);

        if (!toast) {
            toast = document.createElement('div');
            toast.id = `pdf-toast-${vid}`;
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 300px;
            `;
            document.body.appendChild(toast);
        }

        toast.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            toast.innerHTML = '';
        }, 5000);
    }

    /**
     * Escape HTML entities
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    pdfPrevPage() {
        if (this.pdfPage > 1) {
            this.pdfPage--;
            this.renderPdfPage();
        }
    }
    
    pdfNextPage() {
        if (this.pdfDoc && this.pdfPage < this.pdfDoc.numPages) {
            this.pdfPage++;
            this.renderPdfPage();
        }
    }
    
    pdfZoom(delta) {
        this.pdfScale = Math.max(0.5, Math.min(3, this.pdfScale + delta));
        this.renderPdfPage();
    }
    
    // ========================================================================
    // Model Viewer (3D)
    // ========================================================================
    
    async initModelViewer() {
        // Load model-viewer if not loaded
        if (!customElements.get('model-viewer')) {
            await this.loadScript('https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js', 'module');
        }
        
        this.loaded.modelViewer = true;
    }
    
    // ========================================================================
    // Annotorious
    // ========================================================================
    
    async initAnnotorious() {
        if (!this.osdViewer || this.annotorious) return;
        
        const path = this.options.frameworkPath;
        
        // Load Annotorious CSS
        if (!document.getElementById('annotorious-css')) {
            const link = document.createElement('link');
            link.id = 'annotorious-css';
            link.rel = 'stylesheet';
            link.href = `${path}/public/viewers/annotorious/annotorious.min.css`;
            document.head.appendChild(link);
        }
        
        // Load Annotorious
        if (!window.Annotorious) {
            await this.loadScript(`${path}/public/viewers/annotorious/openseadragon-annotorious.min.js`);
        }
        
        // Initialize
        this.annotorious = OpenSeadragon.Annotorious(this.osdViewer, {
            locale: 'auto',
            allowEmpty: true,
            widgets: ['COMMENT', 'TAG']
        });
        
        // Load existing annotations
        if (this.annotations.length > 0) {
            this.annotorious.setAnnotations(this.annotations);
        }
        
        // Bind events
        this.annotorious.on('createAnnotation', async (annotation) => {
            await this.saveAnnotation(annotation);
        });
        
        this.annotorious.on('updateAnnotation', async (annotation, previous) => {
            await this.updateAnnotation(annotation);
        });
        
        this.annotorious.on('deleteAnnotation', async (annotation) => {
            await this.deleteAnnotation(annotation);
        });
        
        this.loaded.annotorious = true;
    }
    
    async loadAnnotations() {
        try {
            const response = await fetch(
                `${this.options.baseUrl}/iiif/annotations/object/${this.options.objectId}`
            );
            
            if (response.ok) {
                const data = await response.json();
                this.annotations = data.items || [];
                
                if (this.annotorious && this.annotations.length > 0) {
                    this.annotorious.setAnnotations(this.annotations);
                }
            }
        } catch (error) {
            console.error('Failed to load annotations:', error);
        }
    }
    
    async saveAnnotation(annotation) {
        try {
            const response = await fetch(`${this.options.baseUrl}/iiif/annotations`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ...annotation,
                    object_id: this.options.objectId
                })
            });
            
            if (response.ok) {
                const saved = await response.json();
                // Update local annotation with server ID
                annotation.id = saved.id;
            }
        } catch (error) {
            console.error('Failed to save annotation:', error);
        }
    }
    
    async updateAnnotation(annotation) {
        const id = annotation.id.replace('#', '');
        
        try {
            await fetch(`${this.options.baseUrl}/iiif/annotations/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(annotation)
            });
        } catch (error) {
            console.error('Failed to update annotation:', error);
        }
    }
    
    async deleteAnnotation(annotation) {
        const id = annotation.id.replace('#', '');
        
        try {
            await fetch(`${this.options.baseUrl}/iiif/annotations/${id}`, {
                method: 'DELETE'
            });
        } catch (error) {
            console.error('Failed to delete annotation:', error);
        }
    }
    
    toggleAnnotations() {
        if (this.annotorious) {
            // Toggle annotation mode
            const readOnly = this.annotorious.readOnly;
            this.annotorious.setVisible(!readOnly);
        }
    }
    
    // ========================================================================
    // Controls
    // ========================================================================
    
    toggleFullscreen() {
        const vid = this.viewerId;
        let element;
        
        switch (this.currentViewer) {
            case 'mirador':
                element = document.getElementById(`mirador-wrapper-${vid}`);
                break;
            case 'pdfjs':
                element = document.getElementById(`pdf-wrapper-${vid}`);
                break;
            case 'model-viewer':
                element = document.getElementById(`model-wrapper-${vid}`);
                break;
            default:
                element = document.getElementById(`osd-${vid}`);
        }
        
        if (!document.fullscreenElement) {
            element?.requestFullscreen();
        } else {
            document.exitFullscreen();
        }
    }
    
    openInNewWindow() {
        const path = this.options.frameworkPath;
        const manifest = encodeURIComponent(this.options.manifestUrl);
        
        if (this.currentViewer === 'mirador') {
            window.open(`${path}/public/viewers/mirador/viewer.html?manifest=${manifest}`, '_blank');
        } else {
            window.open(`${path}/public/viewers/openseadragon/viewer.html?manifest=${manifest}`, '_blank');
        }
    }
    
    downloadImage() {
        // Get current image URL and trigger download
        if (this.osdViewer) {
            const tiledImage = this.osdViewer.world.getItemAt(0);
            if (tiledImage) {
                const source = tiledImage.source;
                const downloadUrl = source['@id'] || source.id;
                if (downloadUrl) {
                    window.open(downloadUrl.replace('/info.json', '/full/full/0/default.jpg'), '_blank');
                }
            }
        }
    }
    
    copyManifestUrl() {
        const vid = this.viewerId;
        const btn = document.getElementById(`btn-manifest-${vid}`);
        const url = btn?.dataset.url || this.options.manifestUrl;
        
        navigator.clipboard.writeText(url).then(() => {
            if (btn) {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i>';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-outline-secondary');
                
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                }, 2000);
            }
        });
    }
    
    goToPage(index) {
        if (this.osdViewer && this.osdViewer.world.getItemCount() > 1) {
            this.osdViewer.goToPage(index);
        } else if (this.pdfDoc) {
            this.pdfPage = index + 1;
            this.renderPdfPage();
        }
    }
    
    // ========================================================================
    // Utilities
    // ========================================================================
    
    async fetchManifest() {
        if (this._manifest) return this._manifest;
        
        try {
            const response = await fetch(this.options.manifestUrl);
            this._manifest = await response.json();
            return this._manifest;
        } catch (error) {
            console.error('Failed to fetch manifest:', error);
            return null;
        }
    }
    
    loadScript(src, type = 'text/javascript') {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.type = type;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    showElement(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'block';
    }
    
    hideElement(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }
}

// Export for non-module usage
if (typeof window !== 'undefined') {
    window.IiifViewerManager = IiifViewerManager;
    // Store current instance for global access (used by redaction callbacks)
    window.iiifViewerManager = null;
}
